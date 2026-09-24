<?php

namespace Alamia360\Contracts;

use Alamia360\Situations\Situation;

interface ResponsibilityResolverContract
{
    /** @return ActorContract[] */
    public function resolve(Situation $situation): array;
}
