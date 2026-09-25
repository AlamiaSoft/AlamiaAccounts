Exactly. **That's the missing layer.** Right now you have a *single-turn command interpreter*, not a conversational copilot.

The classifier is being called roughly like:

```text
current_user_message
        ↓
classifier
        ↓
capability
        ↓
execute
```

It needs to become:

```text
recent conversation
        +
current user message
        ↓
conversation-aware semantic interpretation
        ↓
capability + arguments + references
        ↓
resolve against conversation + database
        ↓
execute
        ↓
response
```

### The last 2–3 turns should be first-class input

For example:

```text
User: Who is IZOC?
Copilot: IZOC appears in 3 transactions...

User: who is she?
```

The classifier should receive enough recent context to understand that **“she” is probably referring to a person/entity discussed immediately before**.

Another:

```text
User: Why did we pay Dog 25000 last Friday?
Copilot: [whatever result]

User: who made that payment?
```

The second message should not go through the system as an isolated:

```text
party.lookup("who made that payment?")
```

It should understand:

```text
that payment
    ↓
the Dog 25,000 transaction from previous turn
    ↓
retrieve transaction audit metadata
    ↓
created_by / posted_by
```

And:

```text
User: Show voucher OB-2026-001
Copilot: [voucher]

User: change the narration
```

should inherit:

```text
voucher = OB-2026-001
action = modify narration
```

rather than searching for `"change the narration"`.

---

## I would add a dedicated Conversation Context layer

Not another giant intent system.

```text
                    Recent conversation
                           │
                           ▼
                  ┌──────────────────┐
                  │ Conversation     │
                  │ Context Builder  │
                  └────────┬─────────┘
                           │
                           ▼
User message ───────► Semantic Interpreter
                           │
                           ▼
                 capability + arguments
                           │
                           ▼
                  Context / Reference
                     Resolution
                           │
                           ▼
                    Capability Router
                           │
                           ▼
                     Domain Services
```

### Context should retain structured facts, not just raw chat

For the last few turns, maintain something like:

```json
{
  "active_subject": {
    "type": "transaction",
    "id": "SV-2026-112"
  },
  "active_party": {
    "type": "organization",
    "id": 42,
    "name": "IZOC Pvt Ltd"
  },
  "active_voucher": {
    "id": "SV-2026-112",
    "reference": "SV-2026-112"
  },
  "active_query": {
    "amount": 100000,
    "direction": "outgoing"
  },
  "last_capability": "transaction.search",
  "last_result": {
    "type": "transaction",
    "ids": ["..."]
  }
}
```

Then:

> `who made that payment?`

doesn't need to rediscover what “that payment” means from scratch.

---

## And don't limit context to exactly 2–3 messages

I'd use **two forms of context**:

### 1. Recent conversational window

Last ~3–6 user/assistant turns.

Useful for:

* she/he/they
* this/that
* “the payment”
* “that voucher”
* “the second one”
* corrections
* follow-up questions

### 2. Structured conversation state

Persist important entities/results extracted from previous turns.

For example:

```text
Active:
  Party: IZOC Pvt Ltd
  Transaction: SV-2026-112
  Voucher: SV-2026-112
  Query: outgoing payment
```

This is much more reliable than dumping the entire conversation into Qwen every time.

---

# There's another important distinction

The LLM shouldn't necessarily answer:

> “What does `she` refer to?”

by itself.

Give it the recent conversation:

```text
Previous:
User: Who is Ali Raza?
Assistant: Ali Raza is associated with IZOC...

Current:
User: Who is she?
```

It can identify a likely reference, but your application should still resolve the candidate against known entities/context.

So:

```text
LLM = conversational interpretation
Application = reference/entity resolution
```

---

## And corrections become easy

This:

> `i wasn't asking to draft a voucher!!!!!!`

should be interpreted in relation to the immediately preceding turn.

Context:

```text
Previous user:
Why did we pay Dog 25000 last Friday?

Previous assistant:
I prepared a draft voucher...

Current user:
I wasn't asking to draft a voucher!
```

The semantic interpreter can produce something like:

```json
{
  "capability": "conversation.correct",
  "correction": {
    "reject_previous_action": true,
    "intended_mode": "query"
  }
}
```

Then the application can **re-run the previous user request with corrected interpretation**:

```text
Why did we pay Dog 25000 last Friday?
             ↓
transaction.search
```

That's genuinely conversational behavior.

---

# So I would now stop expanding `IntentClassifierService`

Your current problem is no longer primarily:

> “Can Qwen identify the intent?”

It is:

> **“Can the copilot maintain and use a working conversational state?”**

I'd split the responsibility:

| Component                | Responsibility                                      |
| ------------------------ | --------------------------------------------------- |
| **Semantic Interpreter** | Understand current utterance                        |
| **Conversation Context** | Maintain recent turns + active entities/results     |
| **Reference Resolver**   | Resolve `she`, `that payment`, `this company`, etc. |
| **Capability Router**    | Decide which application capability to invoke       |
| **Entity Resolver**      | Map names → actual DB entities                      |
| **Policy/Safety**        | Decide whether requested operation is permitted     |
| **Domain Service**       | Actually query/change accounting data               |
| **Response Generator**   | Explain the result conversationally                 |

That architecture also solves your original concern about **1000 behavioral tests** much better.

You're no longer trying to teach the classifier every possible sentence.

You're giving it the ingredients required to understand **new sentences in context**.
