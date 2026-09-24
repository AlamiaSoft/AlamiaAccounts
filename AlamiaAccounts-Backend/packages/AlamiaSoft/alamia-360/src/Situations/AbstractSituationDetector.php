<?php

namespace Alamia360\Situations;

use Alamia360\Contracts\SituationDetectorContract;
use Alamia360\Observation\Observation;

/**
 * Convenient base class for detectors. Subclasses declare which observation
 * types they handle (empty = all) and implement evaluate() — the type filter
 * is applied automatically before evaluate() is called.
 *
 * For detectors that cannot extend this class, implement
 * SituationDetectorContract directly.
 */
abstract class AbstractSituationDetector implements SituationDetectorContract
{
    /**
     * Return the observation types this detector handles.
     * An empty array means the detector is called for every observation.
     *
     * @return string[]
     */
    protected function handles(): array
    {
        return [];
    }

    final public function detect(Observation $observation): ?Situation
    {
        $handles = $this->handles();

        if (!empty($handles) && !in_array($observation->type, $handles, true)) {
            return null;
        }

        return $this->evaluate($observation);
    }

    /**
     * Evaluate whether the observation represents a meaningful operational
     * situation. Return a new Situation if it does, or null to discard.
     */
    abstract protected function evaluate(Observation $observation): ?Situation;
}
