## Core principle: closed-world, not open-ended agent

Most of the "whack-a-mole" comes from treating the copilot as a general-purpose agent that tries to be helpful with anything. For accounting software, invert that: **the copilot can only ever do the finite set of things in your capability table — everything else is a defined, deterministic "out of scope" response, not a best-effort attempt.**

Concretely:

- Capability classification must return a **confidence score**, and there must be a hard **confidence threshold**. Below it → a fixed out-of-scope/clarification card. Never let the system "best-guess" its way into executing a low-confidence capability match.
- **Critical, specific fix from the transcript**: "why we paid Dog 2000$ in the last century" should never have defaulted to `voucher.draft`. If your unknown-intent fallback is currently "attempt a draft since it looks vaguely voucher-shaped," that's backwards. The default for anything ambiguous or unresolved must be `general.help`/`unknown` — drafting should require *positive, confident, fully-resolved* matching, never be the fallback of last resort.

## Tier your capabilities by risk, not just by type

| Tier | Capabilities | Policy |
|---|---|---|
| **Read-only, low risk** | `account.lookup`, `voucher.lookup`, `party.lookup`, reports, `alerts.list` | Can be more lenient/fuzzy — worst case is a wrong answer shown, not a financial artifact created. |
| **Write, high risk** | `voucher.draft`, `voucher.reverse` | Strict: all referenced entities/accounts must be resolved to real records (Priority 0 from before), amount/date must pass plausibility bounds, and — separately from resolution — require a **maker-checker step**: the copilot never posts, only drafts; posting requires an explicit human action, and ideally a second authorized user for anything above a configurable amount threshold. This is standard accounting-software practice (segregation of duties) and it's a natural fit here since you already show drafts before posting. |
| **Destructive** | `voucher.reverse`, any deletion-adjacent request | Always routes to `safety_policy` explanation + compensating-entry workflow, never silent action, regardless of phrasing confidence. |

## Make refusal a first-class, tested behavior

Define explicitly, in writing, what Taliya **will not** do — and treat these as contract test cases exactly like the 42 capability scenarios:
- No chit-chat / general knowledge / opinions.
- No tax or accounting *advice* (liability exposure — it can retrieve/report, not advise).
- No answering about data you don't track (e.g., "who worked on this voucher" if there's no user/audit-trail field — fixed "I don't have that information" response, never fabricate).
- No execution on low-confidence or partially-resolved capability matches.
- No cross-tenant anything, ever, regardless of how the request is phrased.

Each of these should have its own deterministic response template, so "I can't help with that" becomes a designed, tested outcome — not the accidental byproduct of a failed classifier. This is what actually stops the infinite edge-case chase: you stop trying to make every input map to *something useful*, and instead make "this is out of scope" cheap, common, and correct.

## Let the test suite *be* the definition of scope

Practically: any new capability request from the business should come with (a) the capability + happy path, and (b) at least 2-3 refusal/out-of-bounds test cases for it, added to the same contract suite. If it's not in the suite, it's not in scope — this gives you a living, enforceable boundary instead of a document that drifts from the implementation.

**One-line instruction for the agent**: *"Unknown or low-confidence input must resolve to a fixed out-of-scope response, never to the nearest-sounding capability — especially never to `voucher.draft`. Drafting requires positive resolution of every required field; posting requires explicit human confirmation always, and dual confirmation above a configurable amount. Write these as new contract test cases before marking the fix complete."*