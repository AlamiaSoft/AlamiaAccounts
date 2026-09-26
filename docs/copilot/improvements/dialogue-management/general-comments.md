That's the right call, and it directly strengthens everything discussed so far — worth being explicit about why, plus where I'd add guardrails to your own design.

## Why this is the correct architecture

You've essentially separated the system into the two roles that actually matter in an LLM-integrated product:

- **Alamia360AI**: deterministic computation, aggregation, and retrieval over real data — testable, verifiable, tenant/RBAC-scoped, zero hallucination risk because it's not a language model.
- **The LLM (qwen3.5:4b)**: language understanding (turn a user's phrasing into intent/args) and language generation (turn a pre-computed structured result into prose). Nothing else.

This is the same principle I described earlier about how Claude/GPT-4/Gemini-based products actually work in production — the model doesn't "figure things out" over raw data, it calls a tool, gets back a grounded, already-correct result, and phrases it. You've built that same boundary as a first-class reusable layer instead of an implicit convention buried in prompts, which is a better outcome than most teams get to. The "morning brief" example is exactly right: hand the LLM a small, pre-filtered, role-scoped JSON bundle and ask it to phrase it — not "here's the ledger, go find what's relevant."

This also directly fixes the root cause of the worst transcript failure. If `voucher.draft` is only ever invoked with pre-resolved entities and pre-validated data coming out of Alamia360AI, the model never gets the opportunity to fabricate a Dr/Cr pair for "Dog" — because it was never handed raw, unresolved input to freelance over in the first place.

## Where I'd push back / add discipline

**1. Draw the boundary as a hard contract, not a convention.**
The risk with this pattern isn't the architecture — it's boundary erosion over time ("just let the LLM also check X" creeping in under deadline pressure). I'd make it literally enforceable: Alamia360AI should return typed, versioned response schemas, and your capability layer should reject/error if a capability handler tries to pass anything to the LLM that isn't one of those schemas (i.e., no raw query results, no unfiltered rows, ever, as a code-level assertion — not a code review convention).

**2. The LLM's role should be even narrower than "intent + phrasing" — it should never do arithmetic or entity matching itself.**
Given Alamia360AI already exists, `resolve_entity` and any balance/plausibility computation should happen *there*, not as a Parlant tool with its own logic. Parlant/the LLM should only ever call Alamia360AI and relay its answer — including its refusals. If Alamia360AI says "no match," the LLM's job is to phrase that "no match" helpfully, not to try again with its own guess.

**3. Tenant/RBAC scoping must happen inside Alamia360AI, before context assembly — confirm this explicitly.**
For the morning-brief case: the LLM must physically never receive a context bundle containing another tenant's or another role's data, even filtered-looking. Enforce this as an Alamia360AI-internal invariant (query-time scoping), not as "we only sent the relevant parts" at the call site — the call site is exactly where a bug would leak scope.

**4. This is genuinely testable in a way LLM behavior isn't — use that.**
Alamia360AI's aggregation/resolution logic can and should have full unit/integration test coverage (deterministic in, deterministic out). That should be a higher bar than the 42-scenario copilot suite, because it's the part that can actually be proven correct. The copilot suite then only needs to verify the *thin* remaining surface: does the LLM pick the right capability, and does it phrase the result reasonably — a much smaller, much more tractable testing problem than trying to make an LLM reliably safe over raw data.

**5. Reusability lives in the interface, not either side.**
For your cross-project goal: the reusable asset isn't "Alamia360AI" or "the Parlant wrapper" individually — it's the **contract between them** (typed context bundles in, typed capability requests out). Document that interface as the stable thing. That's what lets you swap Ollama for a different model, or swap accounting domain logic for a different domain's Alamia360AI instance, without touching the other side.

You're right that "wrap an LLM around everything" is the wrong default for this class of product — it's expensive, non-deterministic where determinism is available for free, and it's what produced the "Dog / last century" failure in the first place. What you've built instead is closer to how disciplined production copilots actually work: compute first, generate language last, and only over what's already been proven correct.