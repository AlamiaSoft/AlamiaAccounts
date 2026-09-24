# Architecture

## What Alamia 360 Is

Alamia 360 is an application-aware operational intelligence architecture for Laravel. It gives a Laravel application the ability to expose its semantic model, organizational data, operational state, events, responsibilities, and capabilities through one consistent path — to humans, AI agents, and system actors alike.

The core philosophy is that AI-native applications should expose **business meaning**, not database data. Rather than feeding raw table rows to an AI model, Alamia 360 structures what the application *knows* — its entities, relationships, roles, and current situations — into a well-defined operational context that any consumer can interpret. The architecture is **deterministic-first**: rules and domain logic handle what they can, and AI is engaged only where genuine reasoning adds value.

---

## The Three Architecture Layers

```
Application
     │
     ▼
┌─────────────────────────────┐
│ Organizational Data Fabric  │  ← SemanticRegistry, EntityDefinition, CapabilityRegistry
│ Semantic / Entities / Roles │     ResponsibilityRegistry, ActorRegistry
│ Responsibilities / Policies │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│ Event & Change Intelligence │  ← Observer, Observation, SituationDetector, SituationRegistry
│ Events / State / Situations │
└──────────────┬──────────────┘
               │
               ▼
┌─────────────────────────────┐
│ Agent / Orchestration Layer │  ← Orchestrator, CapabilityExecutor, AuditService
│ Observe→Reason→Plan→Act     │
└──────────────┬──────────────┘
               │
       ┌───────┼────────┐
       ▼       ▼        ▼
     Human    AI      System
```

**Layer 1 — Organizational Data Fabric** is the static definition layer. It describes what the application is: its entities, the relationships between them, the capabilities it exposes, and the responsibilities that belong to roles. Nothing here changes at runtime; this layer is registered on boot.

**Layer 2 — Event & Change Intelligence** is the dynamic observation layer. It watches the application for changes — domain events, state scans, scheduled checks, external signals — and interprets them as `Situation` objects: named, prioritized, subject-bound conditions that require attention.

**Layer 3 — Agent / Orchestration Layer** is the action layer. Given a `Situation` and an `Actor`, the `Orchestrator` plans which capability to invoke, enforces authorization, executes the capability through the `CapabilityExecutor`, and records the outcome in the `AuditService`.

---

## Framework Independence

Every cross-cutting concern in Alamia 360 is expressed as a contract (PHP interface):

- `ActorContract` — what an actor is
- `AiProviderContract` — how to reason or classify with a model
- `ResponsibilityResolverContract` — how to determine who should act
- `SituationDetectorContract` — how to detect situations from observations
- `PersistenceContract` — how to store observations, situations, and audit records

The Laravel service container binds concrete implementations to these contracts by default. Because the surface area exposed to the host application is all contract-based, future runtimes — a Node.js edge worker, a Python micro-service — can implement the same contracts and participate in the same operational model without rewriting domain logic.

---

## Core Design Principles

| Principle | Description |
|---|---|
| **Deterministic-first** | Use rules, lookup tables, and domain logic wherever the answer is knowable. Engage AI only when genuine reasoning is required. |
| **Least privilege** | Actors declare capabilities explicitly. Authorization is enforced at every execution point. AI actors receive no automatic elevation. |
| **Auditability** | Every observation is persisted. Every capability execution is logged. The full lifecycle from event to action is reconstructible from the audit trail. |
| **Separation of concerns** | Capabilities are thin wrappers. Business logic lives in application services. Alamia 360 routes and audits; it does not own domain behaviour. |
| **Idempotency** | Duplicate open situations for the same type and subject are deduplicated. Observations may arrive more than once without creating phantom situations. |
| **AI as a consumer** | Alamia 360 is not AI middleware. AI can consume the operational context it produces, but every component runs correctly with `NullAiProvider` installed. |

---

## How the Layers Relate

Data flows downward through the layers; control flows back upward as actions:

| Step | Layer | What Happens |
|---|---|---|
| 1 | Fabric | Entities, capabilities, responsibilities, and actors are registered on boot. |
| 2 | Intelligence | A domain event, state scan, or schedule fires. An `Observation` is created and persisted. |
| 3 | Intelligence | The `Observer` passes the observation to registered `SituationDetector` instances. |
| 4 | Intelligence | A detector produces a `Situation` (or `null`). The situation is recorded in `SituationRegistry`. |
| 5 | Orchestration | The `Orchestrator` receives the situation and an actor. It resolves who is responsible, plans an action (deterministic or AI-assisted), and authorizes the actor. |
| 6 | Orchestration | `CapabilityExecutor` invokes the capability handler, records the audit entry, and returns an `ActionOutcome`. |
| 7 | Consumer | The outcome is delivered to the human, AI agent, or system that initiated the request. |

See [Event & Change Intelligence](./event-change-intelligence.md), [Situations](./situations.md), and [Orchestration](./orchestration.md) for detailed documentation on each layer.
