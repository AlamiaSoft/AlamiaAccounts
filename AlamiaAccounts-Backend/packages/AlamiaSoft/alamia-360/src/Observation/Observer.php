<?php

namespace Alamia360\Observation;

use Alamia360\Contracts\ObserverContract;
use Alamia360\Contracts\SituationDetectorContract;
use Alamia360\Jobs\ProcessObservation;
use Alamia360\Situations\SituationRegistry;

/**
 * Receives observations from any source (Laravel events, scheduled scans,
 * external webhooks, user actions) and:
 *
 *   1. Persists the observation synchronously (full audit trail, always).
 *   2. Runs detectors to surface operationally meaningful Situations —
 *      either inline (default) or async via a queue job.
 *
 * Works fully without an LLM — detectors are typically deterministic/rule-based.
 */
class Observer implements ObserverContract
{
    /** @var SituationDetectorContract[] */
    protected array $detectors = [];

    protected ?\Closure $persister = null;

    protected bool $useQueue = false;

    protected ?string $queueConnection = null;

    public function __construct(protected SituationRegistry $situations)
    {
    }

    /**
     * Wire the Eloquent observation persister. Called by ServiceProvider when
     * alamia360.observation.persist = true.
     */
    public function persistUsing(\Closure $persister): static
    {
        $this->persister = $persister;
        return $this;
    }

    /**
     * Switch to async detection via a queue job.
     * Called by ServiceProvider when alamia360.observation.queue = true.
     */
    public function queueDetection(bool $enable = true, ?string $connection = null): static
    {
        $this->useQueue = $enable;
        $this->queueConnection = $connection;
        return $this;
    }

    public function addDetector(SituationDetectorContract $detector): static
    {
        $this->detectors[] = $detector;
        return $this;
    }

    /** @return SituationDetectorContract[] */
    public function detectors(): array
    {
        return $this->detectors;
    }

    public function observe(Observation $observation): void
    {
        // Step 1: persist observation synchronously (always, if configured).
        // This guarantees the audit trail even if the queue worker fails later.
        if ($this->persister) {
            ($this->persister)($observation);
        }

        // Step 2: run detectors — inline or via queue.
        if ($this->useQueue) {
            $job = new ProcessObservation($observation);

            if ($this->queueConnection) {
                $job = $job->onConnection($this->queueConnection);
            }

            dispatch($job);
        } else {
            $this->runDetectors($observation);
        }
    }

    /**
     * Run all registered detectors against an observation.
     * Called directly (sync) or by ProcessObservation (async).
     */
    public function runDetectors(Observation $observation): void
    {
        foreach ($this->detectors as $detector) {
            $situation = $detector->detect($observation);

            if ($situation !== null) {
                $this->situations->record($situation);
            }
        }
    }
}
