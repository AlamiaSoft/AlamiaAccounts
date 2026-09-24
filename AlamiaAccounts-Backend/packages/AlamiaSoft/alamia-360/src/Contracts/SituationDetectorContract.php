<?php

namespace Alamia360\Contracts;

use Alamia360\Observation\Observation;
use Alamia360\Situations\Situation;

interface SituationDetectorContract
{
    public function detect(Observation $observation): ?Situation;
}
