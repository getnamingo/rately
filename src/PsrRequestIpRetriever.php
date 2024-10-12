<?php

declare(strict_types=1);

namespace Namingo\Rately;

use Namingo\Rately\Internal\RuleIdentityRetrieverInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author Sailaubek Nariman <sailaubek.nar@gmail.com>
 */
final class PsrRequestIpRetriever implements RuleIdentityRetrieverInterface
{
    public function __construct(
        private readonly ServerRequestInterface $request,
    ) {
    }

    public function getIdentity(): string
    {
        $params = $this->request->getServerParams();

        if (!empty($params['HTTP_CLIENT_IP'])) {
            return $params['HTTP_CLIENT_IP'];
        } elseif (!empty($params['HTTP_X_FORWARDED_FOR'])) {
            return $params['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($params['REMOTE_ADDR'])) {
            return $params['REMOTE_ADDR'];
        }

        throw new \RuntimeException('Can\'t detect the IP from request');
    }
}
