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

check($rately->cleanup() >= 0, 'cleanup returns count');
echo "Rately smoke OK\n";
