Good news first, then the real gap.

## Genuine progress
- **No more fabricated drafts.** "Change its amount to 50,000" and the repeated correction request no longer produce a hallucinated `JV-` with wrong Dr/Cr and a "Balanced ✓" card. The P0 gate is holding — this was the highest-severity issue and it appears fixed.
- **Immutability guard fires correctly** on both "delete all vouchers of izoc" and the direct "change the amount to 50,000" mutation attempt, with the right GAAP explanation and reversal pointer.
- **"who is izoc?" still resolves correctly** — the working entity-resolution path hasn't regressed.

## The real problem: safety fixed, usability didn't move — and the agent's audit missed it

Every phrasing variant that isn't the exact "who is X?" pattern still fails, identically to before:
- "can you find me the voucher related to izoc?" → fallback
- "what we have for izoc?" → fallback
- "need to correct the amount of a voucher... related to Izoc" → fallback

This is the same phrasing-brittleness flagged last round, unresolved. The "56/56 passing" result is a **test-suite coverage gap, not evidence of a working system** — the suite is clearly testing the phrasings the team thinks to write, not the phrasings a real user actually types. That's a dangerous gap to be reporting as "100% PASS" without caveat.

## The dead end — this is the one to lead with

Walk the last three turns:
1. User: "change the amount to 50,000" → correctly told: immutable, use a reversal or adjusting `JV`.
2. User: **"but we never got 100,000, correct amount is 50,000; how do i fix that?"** → generic out-of-scope fallback.

The system told the user the correct procedure, then failed to recognize the user's very next, extremely natural follow-up asking to execute it. There is still no `voucher.reverse`-initiating intent that catches "how do I fix that" in context, or any capability at all for **the single most common real bookkeeping task in this entire demo** — correcting a wrong voucher amount. The user is now stuck: told what's not allowed, never shown what to actually click or say next, and the conversation just loops back to the canned refusal. Blocking the dangerous action without completing the safe path to the correct one is only half the fix — right now it's a wall, not a redirect.

## What to tell the agent

1. **Priority 1**: Build the actual guided path — when the immutability card fires, the follow-up ("how do I fix that", "ok how", "yes do that") must route into `voucher.reverse` (pre-filled with the referenced voucher from session context) rather than falling to generic fallback. This is Failure Mode 1 (anaphora/session grounding) *plus* a missing capability wiring — both still open.
2. **Priority 2**: The confidence-threshold split for read-only capabilities (recommended last round) evidently wasn't applied, or wasn't applied broadly enough — "find me the voucher related to X" and "what we have for X" should hit `transaction.search`/`voucher.lookup` with the same success rate as "who is X". Test with a deliberately wide phrasing set, not a fixed template.
3. **Priority 3**: Treat "56/56 passing" as insufficient evidence going forward. Require the *exact literal transcripts* from these review sessions to be added verbatim as regression cases before any pass-rate claim is trusted — the suite has now twice failed to catch a real, reproducible failure that a two-minute manual conversation surfaces immediately. That gap itself is worth raising with the agent as a process issue, not just a code issue.

## Is it just about classification?
but i think: Priority 1: Build the actual guided path — when the immutability card fires, the follow-up ("how do I fix that", "ok how", "yes do that") must route into `voucher.reverse` (pre-filled with the referenced voucher from session context) rather than falling to generic fallback. This is Failure Mode 1 (anaphora/session grounding) plus a missing capability wiring — both still open.

this is related to classification no? I mean the ai should have suggested what the user's intent is? e.g.  find me the voucher related to X should be interpreted by the qwen3.5 as user is trying to search a voucher for x; this should be communicated to the copilot and then the copilot should trigger that tool and present the search result

You're right that it's fundamentally a classification problem — but I'd refine it, because "the AI should classify it correctly" undersells two things that have to be true *before* classification alone can fix this.

## Yes — case 1 is pure classification (the easy fix)

"find me the voucher related to X" → qwen recognizes `voucher.lookup`/`transaction.search` intent, extracts `X` as the entity, calls the tool. This is exactly what you describe, and it's the same pipeline that already works for "who is X?" — it's a recall/prompt-tuning problem on the classifier, not missing architecture. This one is just: better guideline/examples so qwen generalizes across phrasings instead of pattern-matching one template.

## "How do I fix that" is a harder case — classification needs an input it currently isn't getting

Here's the distinction: classification only works if the classifier can see what it needs to classify *against*. "How do I fix that" carries zero identifiable intent on its own — no voucher reference, no capability keyword, nothing. The only way to correctly classify it is if the model is given, as part of its input, **the fact that the immediately preceding turn was a `safety_immutability` refusal for voucher SV-2026-112.**

So the fix isn't "make qwen smarter at classifying this sentence" — it's "make sure qwen is being shown the right context to classify it at all." Two concrete things to check with the agent:

1. **Is prior-turn capability output actually in the classification context, structured, every turn?** — not just raw chat history, but something like "last capability executed: safety_immutability, subject: SV-2026-112." If Parlant's event timeline only carries message text and not structured capability results, qwen has no signal that "that" refers to a specific voucher and a specific blocked action. This is the anaphora/session-grounding half.

2. **Does a target capability exist for qwen to route to, even if it classifies correctly?** — if qwen correctly infers "user wants to fix voucher SV-2026-112's amount," is there an actual registered capability/tool that means "guide user through reversal + corrected entry for a specific voucher," pre-filled with that voucher reference? If the only existing path is `voucher.reverse` triggered by an explicit button click or an explicit voucher number typed by the user, then correct classification still has nowhere to go. This is the "missing capability wiring" half — not a UI nicety, but literally: does the capability catalog have an entry for this intent at all.

So to directly answer your question: yes, better classification fixes case 1 entirely. For case 2, better classification is necessary but not sufficient — it also requires (a) structured prior-turn context being fed into the classifier, and (b) a capability existing to route to once classified correctly. Worth asking the agent to confirm both of those exist before assuming a prompt/guideline tweak alone will close it.



The diagnosis and plan are correct — this is exactly the right split, and Step 1 + Step 2 target the actual root causes rather than symptoms. Before the agent proceeds, flag these gaps:

## 1. `voucher.correct_amount` must go through the same P0 gates as any other write capability — the plan doesn't say this explicitly

Step 2 says "draft the replacement entry with the corrected amount (Rs. 50,000)." Where does 50,000 come from? If it's qwen extracting "50,000" from "correct amount is 50,000; ... 40,000 is still receivable" — that's the exact same free-form numeric extraction that produced the "2,026" bug earlier, now on a new code path that hasn't been hardened yet because it's new.

**Tell the agent**: this capability, being a write path, must pass through the same deterministic amount-validation gate discussed earlier (regex/currency-marker extraction cross-checked against the raw text, not trusted from LLM inference alone), the same leaf-account validation, and the same maker-checker threshold check — *especially* since this transaction is ≥ Rs. 50,000 and the original was Rs. 100,000, both at or above the Rs. 100,000 maker-checker threshold you defined earlier. A "guided" workflow is not an exemption from the write-path invariants; if anything it needs them more, because it's now the only path this user has to resolve their problem, so it will get used often.

## 2. Structured context introduces a new failure mode: "context stickiness" — the plan needs an expiry/reset rule

Binding `active_voucher = SV-2026-112` into classifier context solves the dead-end, but nothing in Step 1 says when that binding clears. If it doesn't expire or reset, a later unrelated message in the same session ("what's the balance of Meezan Bank?") could get incorrectly bound to the stale `active_voucher`/`target_entity`, dragging you back into phrasing-dependent brittleness in the opposite direction.

**Ask the agent to specify**: an explicit reset rule — e.g., `active_voucher`/`last_capability` context clears when a new entity is unambiguously resolved, when N turns pass without reference to it, or when a new top-level capability fires successfully. This should be a named, tested rule, not implicit behavior.

## 3. The confidence numbers (0.50 / 0.75) are asserted, not derived — say so before accepting them

These thresholds are reasonable starting points but are currently just numbers picked by the agent, not validated against your data. Fine as a first pass, but flag that they should be tuned against the expanded verbatim-transcript regression suite (Step 4) — if legitimate read-only queries still fail at 0.50, or unsafe drafts still slip through at 0.75, the numbers need to move. Ask for the actual pass/fail rate at these thresholds once Step 4's tests exist, not just an assertion that they're set.

## 4. Step 4 test list is good but incomplete — add negative cases, not just the happy path fix

The four turns listed only test that the fix *works*. Also require, in the same batch:
- The "Dog / last century" and "2,026-from-date" adversarial cases from earlier reviews, re-run against the new `voucher.correct_amount` path specifically (since it's new code, it hasn't been tested against these yet).
- A stale-context probe: after `active_voucher` is bound, send an unrelated capability request and confirm it does *not* get pulled into the stale binding (tests gap #2 above).

## Otherwise: proceed

The core architecture in the plan is sound and matches everything discussed — structured context injection for grounding, a real registered capability instead of a dead end, asymmetric confidence by tier, and verbatim regression tests. Approve it with the four additions above folded into Steps 2 and 4 before implementation is marked complete.