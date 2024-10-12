<?php

declare(strict_types=1);

namespace Namingo\Rately\Internal;

/**
 * @author Sailaubek Nariman <sailaubek.nar@gmail.com>
 */
interface RuleIdentityRetrieverInterface
{
    public function getIdentity(): string;
}
