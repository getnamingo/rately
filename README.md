# Rately

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

[![SWUbanner](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner2-direct.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

A small Swoole Table-backed fixed-window rate limiter.

```php
use Namingo\Rately\Rately;

// Construct once, before forking Swoole workers, and share the instance.
$rately = new Rately(); // Optional: new Rately(size: 16384)

if ($rately->isRateLimited('domain-check', $clientIp, 100, 60)) {
    // Respond with HTTP 429 / an appropriate EPP error.
}
```

## Compatibility

The existing `new Rately()` and
`isRateLimited(string $serviceKey, string $identifier, int $limit, int $period): bool`
calls are unchanged. Calls 1 through `limit` are allowed; subsequent calls in
the same window are denied, without consuming additional quota. Limits and
periods must be positive integers (`InvalidArgumentException` otherwise).

**Timing change:** previous releases started a period with each identifier's
first request; this implementation aligns fixed windows to Unix time. A
60-second window resets at a whole-minute boundary. Window-boundary bursts
may therefore allow up to twice the rate in a short interval.

Requests across workers share counters only if they reuse the **same Rately
instance constructed before worker fork**. Separate processes, independent
instances, and separate servers do not share counters. A mutex protects
first-use initialization and limit checks so simultaneous requests do not
exceed the quota. Do not instantiate a new limiter for every request.

## Capacity and cleanup

Swoole Tables have no TTL. Expired rows are cleaned up automatically when
inserting into a full table. Optionally reclaim them periodically from one
worker:

```php
Swoole\Timer::tick(60_000, static fn () => $rately->cleanup());
```

An exhausted table with no expired rows throws `RuntimeException` (fail
closed). Choose a capacity for the number of unique identities and active
windows. Keys are hashed to fit the Swoole key-length limit.

An existing *created* `Swoole\Table` with an integer `count` column may be
passed as `new Rately(table: $table)`. When using an injected table, share
the same Rately instance across workers; separate limiter instances have
separate mutexes. Old per-identity table entries from earlier versions will
not be read or cleaned up: use a fresh table during upgrades.

Normalize client IPs at the HTTP boundary and trust forwarded IP headers
only from your trusted proxies. Rately is not a distributed limiter, a
sliding-window limiter, or a token bucket.

Run `php tests/smoke.php` to smoke-test when `ext-swoole` is available.

## Support This Project

If you find Rately useful, consider donating:

- [Donate via Stripe](https://donate.stripe.com/7sI2aI4jV3Offn28ww)
- BTC: `bc1q9jhxjlnzv0x4wzxfp8xzc6w289ewggtds54uqa`
- ETH: `0x330c1b148368EE4B8756B176f1766d52132f0Ea8`

## Licensing

Rately is licensed under the MIT License.