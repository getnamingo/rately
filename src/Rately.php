<?php

namespace Namingo\Rately;

use InvalidArgumentException;
use RuntimeException;
use Swoole\Lock;
use Swoole\Table;

class Rately
{
    private Table $table;
    private Lock $lock;

    /**
     * Create this instance before starting Swoole workers, and share it with them.
     * An injected table must have an integer 'count' column and be created already.
     */
    public function __construct(int $size = 1024, ?Table $table = null)
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Table size must be positive.');
        }

        $this->lock = new Lock(SWOOLE_MUTEX);

        if ($table !== null) {
            $this->table = $table;
            return;
        }

        $this->table = new Table($size);
        if (!$this->table->column('count', Table::TYPE_INT, 8)
            || !$this->table->create()) {
            throw new RuntimeException('Unable to create Rately Swoole table.');
        }
    }

    /**
     * Returns true after the configured number of requests in the current
     * Unix-clock-aligned fixed window. Denied requests do not consume quota.
     */
    public function isRateLimited(string $serviceKey, string $identifier, int $limit, int $period): bool
    {
        if ($limit < 1 || $period < 1) {
            throw new InvalidArgumentException('Rate limit and period must be positive.');
        }

        $window = intdiv(time(), $period);
        // Length-prefixed inputs avoid ambiguous service/identifier combinations.
        // Keep keys within Swoole's 63-byte limit even for large periods.
        $hash = substr(hash('sha256', strlen($serviceKey) . ':' . $serviceKey . ':' . $identifier), 0, 32);
        $key = "r:{$period}:{$window}:{$hash}";

        // The mutex protects first-row creation as well as the limit check.
        // Table::incr() is atomic for existing rows, but is not an atomic
        // insert-if-absent operation.
        $this->lock->lock();
        try {
            $row = $this->table->get($key);
            if ($row === false) {
                if (!$this->table->set($key, ['count' => 1])) {
                    $this->cleanupUnlocked();
                    if (!$this->table->set($key, ['count' => 1])) {
                        throw new RuntimeException('Rately table is full; increase its capacity.');
                    }
                }
                return false;
            }

            if ($row['count'] >= $limit) {
                return true;
            }

            if ($this->table->incr($key, 'count') === false) {
                throw new RuntimeException('Unable to increment Rately counter.');
            }
            return false;
        } finally {
            $this->lock->unlock();
        }
    }

    /** Remove expired window entries. Call from one worker on a timer if desired. */
    public function cleanup(): int
    {
        $this->lock->lock();
        try {
            return $this->cleanupUnlocked();
        } finally {
            $this->lock->unlock();
        }
    }

    private function cleanupUnlocked(): int
    {
        $now = time();
        $removed = 0;
        foreach ($this->table as $key => $_row) {
            if (!str_starts_with((string) $key, 'r:')) {
                continue;
            }
            $parts = explode(':', (string) $key, 4);
            if (count($parts) !== 4 || !ctype_digit($parts[1]) || !ctype_digit($parts[2])) {
                continue;
            }
            $period = (int) $parts[1];
            if ($period > 0 && intdiv($now, $period) > (int) $parts[2]
                && $this->table->del((string) $key)) {
                ++$removed;
            }
        }
        return $removed;
    }
}
