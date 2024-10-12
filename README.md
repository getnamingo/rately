# Rately
A simple rate limiter using Swoole Tables.

# Configuration
Package already has simple rule for ip address and identity retriever from swoole and psr request.

```php
    // 5 requests for 1 minute
    $rately = new \Namingo\Rately\Rately(new IpRateLimitRule(5, 60));
```

# Example usage with middleware

```php
use Namingo\Rately\PsrRequestIpRetriever;
use Namingo\Rately\Rately;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimit implements MiddlewareInterface
{
    public function __construct(
        private readonly Rately $rately,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identiy = (new PsrRequestIpRetriever($request))->getIdentity();
        if ($this->rately->isRateLimited($identiy)) {
            return $this->responseFactory->createResponse(429, 'Too many requests');
        }
        
        $this->rately->increment($identiy);
        return $handler->handle($request);
    }
}
```