Yes — this interaction exposes a **more important problem than the classifier itself**.

The first request is handled correctly:

> `delete all accounts` → guardrail

But the second request:

> `delete the wrong narration entered in voucher number: ob-2026-001`

is being interpreted as:

> **"Tell me about voucher OB-2026-001"**

That's semantically wrong.

### What should happen

The system should recognize:

```text
Action: correct/remove narration
Object: posted voucher
Reference: OB-2026-001
```

It should **not simply retrieve the voucher and ask "What would you like to do?"**

A better response would be along the lines of:

> Voucher **OB-2026-001** is a posted voucher. Its narration/description cannot be silently deleted or modified in place.
>
> If the narration is incorrect, I can help you follow the voucher correction/reversal workflow, while preserving the original accounting record.

Then potentially offer:

* **View voucher**
* **Correct narration** — if your accounting system supports a controlled correction
* **Reverse voucher**
* **Cancel**

depending on what your application actually supports.

---

## The classifier needs an explicit action

Currently:

```json
{
  "intent": "INQUIRE_VOUCHER",
  "reference": "OB-2026-001"
}
```

is insufficient.

It should produce something like:

```json
{
  "intent": "VOUCHER_ACTION",
  "action": "EDIT_NARRATION",
  "target_object": "voucher",
  "reference": "OB-2026-001"
}
```

Then the application policy layer decides whether `EDIT_NARRATION` is:

```text
allowed
confirmation_required
prohibited
requires_reversal
```

This is much cleaner than making `RESTRICTED_ACTION` absorb everything.

---

## More importantly: distinguish "retrieve" from "act"

You now have three fundamentally different classes:

| User says                               | Intent            | Next step                  |
| --------------------------------------- | ----------------- | -------------------------- |
| "Show OB-2026-001"                      | `INQUIRE_VOUCHER` | Retrieve                   |
| "Delete OB-2026-001"                    | `VOUCHER_ACTION`  | Policy/guardrail           |
| "Correct narration in OB-2026-001"      | `VOUCHER_ACTION`  | Policy/correction workflow |
| "Reverse OB-2026-001"                   | `VOUCHER_ACTION`  | Reversal workflow          |
| "Change amount in OB-2026-001"          | `VOUCHER_ACTION`  | Policy/guardrail           |
| "What is the narration of OB-2026-001?" | `INQUIRE_VOUCHER` | Retrieve                   |

That separation is important for your copilot architecture.

### And your current UI response is revealing another issue

The response says:

> "What would you like to do with this voucher?"

and exposes:

> View Voucher Details
> Print / Save PDF
> Reverse Voucher

That's effectively **turning an action request into a generic voucher lookup**.

The copilot should preserve the user's intent instead:

```text
User intent
    ↓
Classify
    ↓
Resolve voucher
    ↓
Determine requested action
    ↓
Accounting policy
    ↓
Execute / require confirmation / refuse
```

Not:

```text
User says something involving voucher
    ↓
Find voucher
    ↓
Show voucher card
    ↓
Ask what they want
```

### I'd add this directly to your behavioral tests

```text
21. Delete voucher narration
"delete the wrong narration entered in voucher number OB-2026-001"
→ VOUCHER_ACTION / EDIT_NARRATION
→ must NOT become INQUIRE_VOUCHER

22. Change voucher narration
"change the narration of OB-2026-001"
→ VOUCHER_ACTION / EDIT_NARRATION

23. Remove voucher narration
"remove the narration from OB-2026-001"
→ VOUCHER_ACTION / EDIT_NARRATION

24. Reverse voucher
"reverse OB-2026-001"
→ VOUCHER_ACTION / REVERSE

25. View voucher
"show OB-2026-001"
→ INQUIRE_VOUCHER

26. Voucher narration inquiry
"what is the narration of OB-2026-001?"
→ INQUIRE_VOUCHER
```

**This is a strong signal that your next architectural step should be adding an explicit `action` field rather than adding more keyword heuristics.**
