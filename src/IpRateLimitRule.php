<?php

declare(strict_types=1);

namespace Namingo\Rately;

use Namingo\Rately\Internal\RateLimitRuleInterface;

/**
 * @author Sailaubek Nariman <sailaubek.nar@gmail.com>
 */
final class IpRateLimitRule implements RateLimitRuleInterface
{
    private const SERVICE_NAME = 'ip';

    public function __construct(
        private readonly int $limit,
        private readonly int $period,
    ) {
    }

    public function getServiceName(): string
    {
        return self::SERVICE_NAME;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPeriod(): int
    {
        return $this->period;
    }
}
