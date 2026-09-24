<?php

namespace Alamia360\Context;

use Alamia360\Actors\ActorRegistry;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Contracts\ActorContract;
use Alamia360\Responsibilities\ResponsibilityRegistry;
use Alamia360\Semantic\SemanticRegistry;
use Alamia360\Situations\Situation;

/**
 * Constructs a bounded OperationalContext for a given actor/situation,
 * rather than dumping full application state. This is the explicit
 * "selective context" mechanism required by the architecture.
 */
class ContextBuilder
{
    public function __construct(
        protected SemanticRegistry $semantics,
        protected CapabilityRegistry $capabilities,
        protected ResponsibilityRegistry $responsibilities,
        protected ActorRegistry $actors,
    ) {
    }

    public function forSituation(Situation $situation, ActorContract $actor): OperationalContext
    {
        $availableCapabilities = array_values(array_filter(
            array_map(fn ($c) => $c->name(), $this->capabilities->all()),
            fn ($name) => in_array($name, $actor->capabilities(), true)
        ));

        $responsibility = $this->responsibilities->for($situation->type);

        return new OperationalContext(
            actor: ['id' => $actor->id(), 'type' => $actor->type()->value, 'role' => $actor->role()],
            situation: [
                'type' => $situation->type,
                'summary' => $situation->summary,
                'priority' => $situation->priority->value,
                'status' => $situation->status()->value,
                'subject_type' => $situation->subjectType,
                'subject_id' => $situation->subjectId,
                'detected_at' => $situation->detectedAt()->format(DATE_ATOM),
                'due_at' => $situation->dueAt()?->format(DATE_ATOM),
            ],
            entities: $situation->context,
            responsibilities: $responsibility ? [
                'role' => $responsibility->getRole(),
                'user_id' => $responsibility->getUserId(),
                'department' => $responsibility->getDepartment(),
                'escalation' => $responsibility->getEscalation(),
            ] : [],
            availableCapabilities: $availableCapabilities,
        );
    }
}
