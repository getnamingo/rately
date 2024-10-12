<?php

declare(strict_types=1);

namespace Namingo\Rately;

use Namingo\Rately\Internal\RateLimitRuleInterface;
use Swoole\Table;

class Rately
{
    private $table;

    public function __construct(
        private readonly RateLimitRuleInterface $rateLimitRule
    ) {
        $this->table = new Table(1024);
        $this->table->column('count', Table::TYPE_INT);
        $this->table->column('timestamp', Table::TYPE_INT);
        $this->table->create();
    }

    public function __destruct()
    {
        $this->table->destroy();
    }

    public function isRateLimited(string $identity, int $period): bool
    {
        $key = $this->rateLimitRule->getServiceName() . ":" . $identity;
        $now = time();

        $data = $this->table->get($key);

        if ($data === false || ($data['timestamp'] + $period) <= $now) {
            $this->table->set($key, ['count' => 0, 'timestamp' => $now]);
            return false;
        }

        return $data['count'] > $this->rateLimitRule->getLimit();
    }

    public function increment(string $identity): void
    {
        $key = $this->rateLimitRule->getServiceName() . ":" . $identity;
        $this->table->incr($key, 'count');
    }
}
