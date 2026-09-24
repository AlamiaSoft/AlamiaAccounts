<?php

namespace Alamia360\Jobs;

use Alamia360\Observation\Observation;
use Alamia360\Observation\Observer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when alamia360.observation.queue = true.
 *
 * Decouples the HTTP request from observation detection: the observation is
 * always persisted synchronously (full audit trail), but the detectors that
 * turn it into a Situation run asynchronously in a queue worker.
 *
 * This means:
 *   - Observation persistence:  always synchronous (guarantees the record exists)
 *   - Situation detection:      async (detectors, responsibility resolution, etc.)
 *
 * Queue connection is determined by ALAMIA_QUEUE_CONNECTION env var.
 */
class ProcessObservation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Observation $observation,
    ) {
    }

    public function handle(Observer $observer): void
    {
        $observer->runDetectors($this->observation);
    }
}
