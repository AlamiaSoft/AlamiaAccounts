<?php

namespace Alamia360\Infrastructure\Repositories;

use Alamia360\Infrastructure\Models\AuditEventModel;

/**
 * Writes audit events to the alamia_audit_events table.
 * Injected into AuditService; the AuditService is injected into the
 * CapabilityExecutor's audit hook by the ServiceProvider.
 */
class EloquentAuditRepository
{
    /**
     * @param array{
     *     capability: string,
     *     side_effect: string,
     *     actor_id: string,
     *     actor_type: string,
     *     input: array,
     *     result: mixed,
     *     error: string|null,
     *     at: \DateTimeInterface
     * } $event
     */
    public function record(array $event): AuditEventModel
    {
        return AuditEventModel::create([
            'capability'   => $event['capability'],
            'side_effect'  => $event['side_effect'],
            'actor_id'     => $event['actor_id'],
            'actor_type'   => $event['actor_type'],
            'situation_id' => $event['situation_id'] ?? null,
            'input'        => $event['input'] ?: null,
            'result'       => is_array($event['result']) ? $event['result'] : null,
            'error'        => $event['error'] ?? null,
            'at'           => $event['at'],
        ]);
    }
}
