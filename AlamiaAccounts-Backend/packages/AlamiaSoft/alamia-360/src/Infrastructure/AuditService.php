<?php

namespace Alamia360\Infrastructure;

use Alamia360\Infrastructure\Models\AuditEventModel;
use Alamia360\Infrastructure\Repositories\EloquentAuditRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query and write surface for the audit trail.
 *
 * Writing: wired into CapabilityExecutor::auditUsing() by the ServiceProvider.
 * Querying: inject this service anywhere you need to surface past decisions
 *           (dashboards, compliance reports, AI context assembly, etc.).
 */
class AuditService
{
    public function __construct(
        protected EloquentAuditRepository $repository,
    ) {
    }

    /**
     * Record a capability execution event. Called by the CapabilityExecutor
     * audit hook. Delegates to the Eloquent repository.
     *
     * @param array<string,mixed> $event
     */
    public function record(array $event): void
    {
        $this->repository->record($event);
    }

    /**
     * All audit events for a given actor, newest first.
     */
    public function forActor(string $actorId): Collection
    {
        return AuditEventModel::where('actor_id', $actorId)
            ->orderByDesc('at')
            ->get();
    }

    /**
     * All audit events for a given capability, newest first.
     */
    public function forCapability(string $capability): Collection
    {
        return AuditEventModel::where('capability', $capability)
            ->orderByDesc('at')
            ->get();
    }

    /**
     * All audit events linked to a specific situation row.
     */
    public function forSituation(int $situationId): Collection
    {
        return AuditEventModel::where('situation_id', $situationId)
            ->orderByDesc('at')
            ->get();
    }

    /**
     * All failures (capability executions that produced an error), newest first.
     */
    public function failures(): Collection
    {
        return AuditEventModel::whereNotNull('error')
            ->orderByDesc('at')
            ->get();
    }
}
