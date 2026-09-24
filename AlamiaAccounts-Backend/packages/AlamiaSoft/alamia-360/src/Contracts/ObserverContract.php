<?php

namespace Alamia360\Contracts;

use Alamia360\Observation\Observation;

interface ObserverContract
{
    public function observe(Observation $observation): void;
}
