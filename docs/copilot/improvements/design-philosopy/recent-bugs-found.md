Good instinct — this is the single highest-leverage feature you could build right now, because it turns "me and you manually stress-testing transcripts for six rounds" into something the system does continuously. A few design points worth locking in before the agent builds it, since a naive version of this (just a chat log viewer) will miss most of the value.

## Capture more than "asked / AI said / shown" — capture the whole pipeline

Given everything found in this thread, a flat prompt→response log won't catch the bugs that actually occurred. Each interaction record should capture, per turn:

| Field | Why it matters, given what you've found |
|---|---|
| Raw user input | Baseline |
| Resolved capability + confidence score | Lets you see *why* something misrouted (the phrasing-brittleness bugs) |
| Classification mode (Active/Parlant vs. heuristic fallback) | Directly tests the dual-mode drift concern from last round — if fallback fires more than expected in production, you want to know |
| Structured arguments extracted (entity, amount, reference, date) | This is where the "2,026 from date" and "Dog" bugs lived — without this, you can't tell if a wrong output was a bad capability call or a bad extraction |
| Session/context state at time of call (`active_voucher`, `last_capability`, turns since last mention) | Directly tests context decay/stickiness — you need to see this to catch stale-context bugs like the Meezan Bank/Izoc cross-contamination risk |
| Raw Alamia360AI response (before formatting) | Separates "wrong data returned" from "data formatted wrong" — this would have immediately pinpointed the P&L/Balance Sheet zero-totals bug as a retrieval issue, not a display issue |
| Final rendered card/output shown to user | What the user actually saw |
| Latency per stage (classification vs. execution vs. rendering) | Ties into your existing <1.5s target |
| Tenant/company code + user role | Essential for catching cross-tenant leakage, and for filtering exports safely (see below) |

If you only log input/output/final, you'll see *that* something went wrong but not *why* — which is the whole value of a dev diagnostic tool.

## Make it a ground-truth eval harness, not just a viewer

This is the natural fix for the exact gap the last bug exposed: **nothing currently checks copilot answers against known-correct values.** Extend the design so that:
- Any logged interaction can be **flagged and annotated** (by you, or a reviewer) with the *expected* correct capability/output.
- Flagged interactions become **candidate regression test cases** — ideally with a one-click "promote to `verify_copilot_intents.php`" action, so real production failures feed directly into the test suite instead of living only as a log entry someone has to remember to act on. This closes the loop this whole conversation has been circling: verbatim real transcripts becoming permanent tests.
- Track pass/fail rate over time per capability, so you can see if a fix regresses something else (this is effectively lightweight production monitoring for model drift, which matters more than usual given you're running a small local model that behaviors can shift on with version/prompt changes).

## Security/scope constraints — this is a real risk given the data involved

This tool will contain real financial data, real party names, real amounts, across all tenants if built centrally.
- **RBAC-gate it hard** — internal dev/admin role only, never exposed to end-tenant users, and ideally not accessible cross-tenant by default even for admins (require explicit tenant selection, logged).
- **Decide on redaction/retention policy explicitly** — do you need to store raw amounts/party names indefinitely for dev diagnostics, or can PII/financial specifics be masked after some window while keeping the structural fields (capability, confidence, latency) for trend analysis? Worth deciding deliberately rather than defaulting to "keep everything forever" given this is production accounting data.
- **Export must inherit the same access control** — an exported CSV/JSON sitting in someone's Downloads folder is a bigger leak surface than the UI itself; make sure export doesn't bypass the RBAC gate that views it.

## Suggested structure for the "System Diagnosis" UI section

- **List view**: chronological interactions, filterable by tenant, capability, confidence range, mode (Active/fallback), pass/fail if annotated, and — importantly — a filter for "final output flagged as low confidence or fallback" so you can jump straight to the highest-risk cases without reading everything.
- **Detail view**: the full pipeline table above for one turn, ideally with the session's preceding 3-5 turns for context (since so many bugs here were context-dependent, not single-turn).
- **Export**: JSON (structured, machine-consumable — this is what your dev agent should actually consume to find gaps) and CSV (for a human skimming trends) as separate exports, since they serve different consumers.
- **A running "confidence/discrepancy" flag**: given the Balance Sheet bug, it's worth having the diagnosis view flag any report capability that returned all-zero totals, and any case where the same entity/session produced two different answers to what should be an idempotent question (P&L asked twice should return the same number) — this is a cheap, generic anomaly signal that would have caught the last bug automatically rather than requiring a human to notice the owner's report didn't match.

## One sequencing note

Given the P&L/Balance Sheet bug is still open and higher priority, I'd build the minimal version of this tool (raw capture + list/detail view, no fancy annotation/promotion workflow yet) **specifically to debug that bug first** — you'll get immediate practical proof the tool works, and you'll fix the actual production issue faster than manually re-tracing it through code. Build the eval-harness/regression-promotion layer as a fast-follow once the core capture is proven useful on a real bug.

# bugs

This is exactly the value I predicted from naive testing — and it surfaced something more serious than anything the six adversarial rounds caught, because it's a **read-path failure presented with false confidence**, not a write-path failure that HITL could still catch.

## The critical finding: wrong financial reports labeled "Mathematically Valid"

Walk the turns:
- "How much profit did we make this year?" → P&L card shows **PKR 0 / PKR 0**, tagged "Mathematically Valid."
- "Balance sheet?" → same pattern, PKR 0 / PKR 0, same badge.
- User directly states the real P&L shows **75,000** net profit/loss — the copilot **doesn't react at all**, just re-serves the identical broken zero card.
- User confirms the actual balance sheet also disagrees with what the copilot showed.

This is worse than the earlier fabrication bugs in one specific way: those were caught *before* anything got posted, because Tier 3 write actions go through staging and HITL. **Reports are Tier 2 — presented as-is, no confirmation step, because reads were assumed low-risk.** But the actual owner of the company just asked "how much profit did we make," got a confidently-labeled wrong answer, and the system had no mechanism to notice it disagreed with the real system of record. If he hadn't happened to check the real report, this would have gone uncaught. That reclassifies "read-only = low risk" as **not universally true** — a wrong number stated with false authority to the business owner is a real-world harm even though no ledger state was touched.

## "Mathematically Valid" is actively misleading here

That badge apparently only checks `Dr === Cr` — trivially true for 0 = 0. It says nothing about whether the retrieval is correct. Showing a validity badge on empty/wrong data is strictly worse than showing no badge, because it actively signals trustworthiness on a wrong answer. This needs its own fix independent of the underlying bug: **the badge should never render as "valid" when the underlying totals are suspiciously zero for an entity with known posted activity** (you already have that activity — SV-2026-112 alone is Rs. 100,000, and the actual reports elsewhere in the app clearly show non-zero figures).

## Likely root causes to hand the agent (in order of likelihood)

1. **Period/date-range resolution bug** — "current period"/"this year" in the report capability likely resolves to the wrong fiscal year, wrong date boundary, or an empty range, distinct from whatever the "actual" (non-copilot) report screens use. Compare the exact date range the copilot's `report.profit_loss`/`report.balance_sheet` capability is querying against what the real report generator uses for "this year."
2. **Tenant/company scoping mismatch** — possible the report capability isn't resolving `X-Company-Code`/`domainUuid` the same way voucher/account lookups do, and is querying an empty or wrong tenant context. Worth explicitly re-testing tenant isolation on this capability specifically, since it clearly wasn't covered by the existing 74-test suite.
3. **Stubbed/placeholder report path never fully wired** — given the six rounds of testing exclusively hammered `voucher.*`, `party.lookup`, and anaphora, it's plausible `report.*` capabilities were built early, passed a superficial "does it return a card" check, and never validated for actual data correctness against a known ground truth.

## The structural gap this reveals

**None of the 74 tests validate report output against a known ground truth.** They almost certainly validate "does this input route to `report.profit_loss`" and "is the card schema well-formed" — not "does the number match reality." That's a blind spot in the testing philosophy itself: correctness of retrieval was never adversarially checked for the reporting capabilities, only for the drafting/entity ones. Recommend closing this gap by:
- Adding a fixed, known-good test tenant with pre-seeded transactions and an expected exact P&L/Balance Sheet outcome; assert the copilot's numbers match it exactly, not just that the card renders.
- Adding a rule: **if a report returns all-zero totals for a company/period with any posted journal entries in range, treat that as a data-integrity error, not a valid answer** — return a distinct "unable to verify this report — please check manually" state instead of a confident zero, mirroring the not_found/plausibility pattern already built for entity resolution.
- Testing the exact discrepancy-pushback scenario above as a named case: user states a number that contradicts the copilot — minimum acceptable behavior is *not* repeating the same wrong card; ideally it should flag "let me recheck" rather than staying silent on the contradiction.

## Minor, secondary finding
"I'mMashareq, owner of this company; how will you help me today?" still falls to the generic refusal — an onboarding/self-introduction-plus-capability-question pattern isn't covered by the Tier 1 guidance net yet. Lower severity, but another data point that Tier 1 coverage is narrower than the test suite currently believes.

## Recommended framing for the agent
*"The naive test found a live, confidently-labeled wrong financial report shown to the actual business owner — this is a P0, ahead of anything else in the backlog, because unlike the drafting bugs, there's no HITL step between this answer and a real business decision. Fix the report data-correctness bug, remove the false 'Mathematically Valid' badge on suspicious zero totals, and add ground-truth report tests before touching anything else."*