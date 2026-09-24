<?php

namespace Alamia360\Infrastructure\Repositories;

use Alamia360\Infrastructure\Models\SituationModel;
use Alamia360\Situations\Situation;

/**
 * Persists domain Situation objects to the alamia_situations table.
 * Wired as the persister closure in SituationRegistry by the ServiceProvider
 * when persistence.driver = 'eloquent'.
 *
 * Domain → persistence only. The registry is the authoritative in-memory
 * store; this class provides the durable side-effect.
 */
class EloquentSituationRepository
{
    public function save(Situation $situation): SituationModel
    {
        return SituationModel::create([
            'type'               => $situation->type,
            'summary'            => $situation->summary,
            'priority'           => $situation->priority->value,
            'status'             => $situation->status()->value,
            'subject_type'       => $situation->subjectType,
            'subject_id'         => $situation->subjectId !== null ? (string) $situation->subjectId : null,
            'context'            => $situation->context ?: null,
            'recommended_action' => $situation->recommendedAction(),
            'detected_at'        => $situation->detectedAt(),
            'due_at'             => $situation->dueAt(),
        ]);
    }

    /** @return SituationModel[] */
    public function open(): array
    {
        return SituationModel::where('status', 'open')
            ->orderBy('detected_at', 'desc')
            ->get()
            ->all();
    }

    /** @return SituationModel[] */
    public function ofType(string $type): array
    {
        return SituationModel::where('type', $type)
            ->orderBy('detected_at', 'desc')
            ->get()
            ->all();
    }
}
