<?php

require dirname(__DIR__) . '/src/Rately.php';

use Namingo\Rately\Rately;

if (!extension_loaded('swoole')) {
    fwrite(STDERR, "ext-swoole is required\n");
    exit(1);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$rately = new Rately(1024);
check(!$rately->isRateLimited('service', 'alice', 2, 3600), 'first should pass');
check(!$rately->isRateLimited('service', 'alice', 2, 3600), 'second should pass');
check($rately->isRateLimited('service', 'alice', 2, 3600), 'third should be denied');
check($rately->isRateLimited('service', 'alice', 2, 3600), 'denial remains denied');
check(!$rately->isRateLimited('service', 'bob', 2, 3600), 'different identity should pass');
check(!$rately->isRateLimited('other', 'alice', 2, 3600), 'different service should pass');
check(!$rately->isRateLimited('service', str_repeat('x', 512), 1, 3600), 'long identity should pass');

try {
    $rately->isRateLimited('service', 'alice', 0, 60);
    throw new RuntimeException('Invalid limit was accepted');
} catch (InvalidArgumentException $expected) {
}

// Saturate a separate table with expired window rows. This exercises the
// allocation warning -> automatic cleanup -> retry path under an application
// error handler that promotes warnings to exceptions.
$table = new \Swoole\Table(64);
$table->column('count', \Swoole\Table::TYPE_INT, 8);
check($table->create(), 'test table should be created');
$expiredWindow = intdiv(time(), 60) - 2;
$filled = 0;
set_error_handler(static function (): bool { return true; }, E_WARNING);
try {
    for ($i = 0; $i < 512; ++$i) {
        if (!$table->set("r:60:{$expiredWindow}:" . str_pad((string) $i, 32, '0', STR_PAD_LEFT), ['count' => 1])) {
            break;
        }
        ++$filled;
    }
} finally {
    restore_error_handler();
}
check($filled > 0 && $filled < 512, 'test table should be saturated');
$recovering = new Rately(table: $table);
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
try {
    check(!$recovering->isRateLimited('recovery', 'client', 1, 60), 'expired rows should be reclaimed on allocation failure');
} finally {
    restore_error_handler();
}
check($recovering->cleanup() >= 0, 'cleanup remains usable after warning recovery');

check($rately->cleanup() >= 0, 'cleanup returns count');
echo "Rately smoke OK\n";
