## another thing I am thinking now

; the copilot should be a helpful assistant around: "how to use" the software; not around: "identifying correct capability/tool and then letting user click a button to execute that action"; on a design philosophy level this is better than trying to achieve the goal of: "assist the user by letting ai/copilot automatically execute the work that user had to do"; facilitating the user is correct; so instead of tedious repeated work the ai can assist by taking care of such work itself on behalf of user (but with a HITL, never without it)

## direction

This is the right shift, and it's more than a philosophy statement — it changes what "correct" means for every failure mode discussed so far, and in most cases makes the engineering problem easier, not just safer.

## Why this resolves the core tension, not just the risk

Every hard problem in this thread — phrasing brittleness, the "Dog" fabrication, the "2,026" amount bug, confidence thresholds fighting each other — exists because the system is trying to go **straight from ambiguous natural language to an executed (or execution-ready) financial action.** That's the highest-stakes possible interpretation of unclear input, which is exactly why it needs the most engineering rigor to get right, and exactly why every recall/precision tradeoff bites hard.

Your reframe changes the default interpretation of ambiguous input from *"attempt to execute what they probably meant"* to *"tell them how to do it, and separately, optionally, offer to stage it for them."* That's a fundamentally lower-stakes default. Getting a "how to correct a voucher" explanation slightly wrong is a UX annoyance; getting an auto-drafted financial artifact slightly wrong is the actual danger you've been fighting for six review cycles.

This is the same distinction the industry has converged on with "copilot vs. autopilot" — a copilot suggests, narrates, and prepares; the human remains the pilot for every consequential action. What you're proposing is holding the product to that line deliberately, rather than drifting toward autopilot behavior because it demos better.

## Concrete architectural implication: add a third capability tier

You already have read-only vs. write tiers. Add a tier in front of both:

| Tier | Example | AI's job | Human's job |
|---|---|---|---|
| **Guidance/navigation** (new, should be the default for anything unclear) | "how do I fix a wrong voucher amount?" | Explain the actual workflow in the software (reverse → re-issue), point to the UI location, no data touched | Executes manually, or asks the AI to stage it |
| **Read/lookup** | "who is izoc?" | Retrieve and phrase real data | None — just consumes info |
| **Staged action (HITL)** | "prepare the reversal for SV-2026-112 at 50,000" | Pre-fill a draft for review | Reviews *every field*, edits if wrong, explicitly confirms |

This directly fixes the "conversational dead-end" from the last review, and more simply than the `voucher.correct_amount` plan on the table. Instead of needing the classifier to correctly infer amount, reference, and intent all at once from "but we never got 100,000, correct amount is 50,000, how do I fix that" — the *guidance* response can just be: *"Posted vouchers can't be edited directly. To correct SV-2026-112, you'd reverse it and post a new voucher with the right amount. Want me to prepare that as a draft for you to review?"* That's a much easier target for a 4B model to hit (it's answering a "how do I" question with a known procedure, not extracting structured financial fields under ambiguity), and it puts the harder extraction step (Step 2 in the current plan) behind an explicit, separate, user-confirmed request — which is exactly where confidence and validation checks are cheap to apply because the user has now stated their intent unambiguously.

## The guardrail this needs so it doesn't become theater

One risk with "staged, always HITL": if confirmation becomes a single reflexive button click, you've just moved automation bias one screen over — the user still trusts a wrong pre-filled draft because it *looks* authoritative. This matters more, not less, once staging becomes the primary interaction mode, because it'll be used far more often than the current rare "draft" path.

So HITL should mean **active review of the specific fields that matter**, scaled to risk — not just a "Confirm" button:
- Below the maker-checker threshold: show the full draft, require an explicit "Confirm" click, but the click itself is fine as the friction point.
- At/above the maker-checker threshold, or for reversals: require the user to actually re-enter or explicitly acknowledge the changed field (e.g., amount) rather than rubber-stamping a pre-filled value — cheap to implement, and it's the difference between "human in the loop" and "human near the loop."

## What this means for the classifier's job, concretely

The intent classifier's *default* output for anything not clearly a read-only lookup should shift from "best-guess capability + draft" to "is this a how-to question, a lookup, or an explicit staging request" — with "how-to" as the safe fallback when uncertain, rather than the current out-of-scope refusal *or* (worse, previously) a silent draft attempt. That reframes your confidence-threshold problem too: low confidence should degrade to *guidance*, not to a flat refusal and not to execution — which gives you a third, much safer landing spot for all the ambiguous cases that have been bouncing between "ignored" and "dangerous" this whole review cycle.

**Recommend telling the agent**: add `guidance.how_to` as a first-class capability with its own content (indexed against your actual documented workflows — reversal, drafting, reports, etc.), make it the default landing for low-confidence input instead of generic refusal, and reframe `voucher.correct_amount` from "auto-detect and draft" to "user explicitly requests staging after being told the procedure" — which lets you keep Step 2's plan almost as-is, just triggered by a clearer, safer, easier-to-classify user signal.