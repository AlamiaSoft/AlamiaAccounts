<?php

namespace Alamia360\Infrastructure\Repositories;

use Alamia360\Infrastructure\Models\ObservationModel;
use Alamia360\Observation\Observation;

/**
 * Persists domain Observation objects to the alamia_observations table.
 * Wired as the persister closure in Observer by the ServiceProvider
 * when alamia360.observation.persist = true.
 */
class EloquentObservationRepository
{
    public function save(Observation $observation): ObservationModel
    {
        return ObservationModel::create([
            'type'        => $observation->type,
            'source'      => $observation->source->value,
            'entity_type' => $observation->entityType,
            'entity_id'   => $observation->entityId !== null ? (string) $observation->entityId : null,
            'payload'     => $observation->payload ?: null,
            'observed_at' => $observation->observedAt,
        ]);
    }

    /** @return ObservationModel[] */
    public function forEntity(string $entityType, mixed $entityId): array
    {
        return ObservationModel::where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->orderBy('observed_at', 'desc')
            ->get()
            ->all();
    }

    /** @return ObservationModel[] */
    public function ofType(string $type): array
    {
        return ObservationModel::where('type', $type)
            ->orderBy('observed_at', 'desc')
            ->get()
            ->all();
    }
}
