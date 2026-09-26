This transcript is more revealing than the spec — it shows a **safety failure**, not just a dialogue-fluency gap. Turn by turn:

| Turn | What happened | Diagnosis |
|---|---|---|
| "who Taliya??" | Falls through to generic no-match fallback | No `general.help`/self-identity handling at all — even a trivial fixed intent isn't caught. |
| "what or who is izoc?" | No match | `party.lookup` capability appears broken or nonexistent. |
| "what we have with izoc" | Returns voucher SV-2026-112 | Works only because "IZOC" happens to appear as a literal substring in the voucher's narration text — this is narration string-matching, not entity resolution. Same intent, different phrasing, opposite outcome — classic brittleness. |
| "who worked on this voucher?" | No match, despite SV-2026-112 shown one turn earlier | **This is Failure Mode 1, live.** "this voucher" fails to resolve to the just-displayed reference. There is currently zero cross-turn context carried forward — not degraded anaphora handling, *no* anaphora handling. |
| "why we paid Dog 2000$ in the last century??" | System **drafts and presents a balanced journal voucher**: Dr `4600` / Cr `1130`, Rs. 2,000 | **This is the serious one.** |

## Why the last turn matters most

This isn't a dialogue problem — it's a violation of your own Section 5 invariant ("Unknown persons/contacts must never be arbitrarily mapped to unrelated chart of accounts... No Blind Projections/Hallucinations"), happening in a live draft:

- **"Dog"** is not a real party/contact in any plausible ledger, and nothing in the response shows a lookup attempt or a `not_found`/`safety_policy` card — it silently proceeded to draft.
- **"the last century"** is a temporally absurd, adversarial-sounding qualifier (pre-2000, before the company plausibly existed) — no plausibility check fired.
- The account codes `4600`/`1130` are shown **unresolved** (raw codes, not account names like "1130 Meezan Bank") — meaning even the account-name join is broken, on top of no validation that these accounts are the correct/leaf/postable accounts for this fabricated scenario.
- The system treated a nonsensical, adversarial-looking prompt as a legitimate `voucher.draft` request and generated a confident-looking, "Balanced ✓" financial artifact from nothing.

A draft isn't posted yet, so no ledger damage occurred — but a user could plausibly click through and post a fabricated transaction, and the UI gives zero signal that anything is wrong. This is a materially higher-risk failure than "the copilot didn't understand a pronoun."

## Implication for the architecture decision

This transcript **raises the priority of things I'd already flagged as gaps**, and adds one new one:

1. **Entity/party grounding must run *before* `voucher.draft` fires, unconditionally** — no draft-generation capability should execute without a resolved, real contact/account. This is a code-level gate, not something you can leave to model judgment or to Parlant/any framework alone — it has to be enforced as a hard precondition in your capability dispatcher, independent of which dialogue layer you pick.
2. **Plausibility/safety checks on drafts are currently absent** — unresolvable party, absurd dates, or unverifiable amounts should trigger `safety_policy` or a confirmation card, never silent draft generation. This directly matches the deterministic-guardrail requirement in your spec (§3.5, §7 criterion 4) — and it's currently not implemented at all, not just weak.
3. **The anaphora failure is exactly as severe as documented** — confirms Parlant's event-timeline/session model (or equivalent context carryover) is necessary, not optional.
4. **Account-name resolution is also broken independent of AI** — `4600`/`1130` rendering as raw codes instead of names suggests a display/join bug in the existing dispatcher worth fixing regardless of framework choice.

**Recommended sequencing**: treat #1 and #2 (block-drafts-on-unresolved-entities, plausibility gate) as an urgent fix ahead of — and independent of — the Parlant/framework integration, since this is a live financial-integrity gap, not a UX complaint. The framework decision addresses fluency and repair; it won't by itself stop a copilot from drafting vouchers for imaginary vendors.

## Further Guidance
Good — the plan has the right components (`resolve_entity`, guidelines, capability gateway). The transcript shows the implementation isn't enforcing them where it matters. Here's what to hand back to the agent, in priority order.

## Priority 0 — Move safety enforcement out of Parlant, into `alamia-360`

This is the most important structural fix. Right now the plan puts entity resolution and immutability checks as **Parlant guidelines** — i.e., behavior the LLM is nudged toward. With a 4B local model, guideline-following will not be 100% reliable turn over turn, and the "Dog / last century" transcript is proof: whatever guideline was supposed to gate `voucher.draft` on a resolved party didn't fire, and nothing stopped it.

**Directive**: `VoucherDraftCapability.php` itself must refuse to execute unless these are true, in code, independent of what Parlant decided:
1. `resolve_entity` returned an actual matched contact/party record (not a raw string) — if unresolved, return a `party_not_found`/`safety_policy` card, never fabricate a mapping.
2. Both debit and credit accounts resolve to real, `category: false` (leaf/postable) accounts in this tenant's COA — if the tool passes an unresolved or category account, reject before drafting.
3. The transaction date passes a plausibility check — inside the tenant's active fiscal calendar and not before company inception (config-driven, not hardcoded). "Last century" should fail this check outright.
4. Amount is a real parsed numeric value tied to the user's actual utterance, not inferred.

Parlant's guidelines stay as the **conversational layer** (deciding *when* to call the tool, handling repair/anaphora) — but the capability executor is the last line of defense and must not trust the sidecar's judgment for anything touching invariants in §3/§5 of your spec. This is a defense-in-depth split, not redundant work.

## Priority 1 — Fix the specific transcript failures

| Observed failure | Root cause to check | Fix |
|---|---|---|
| "who Taliya??" → no match | No `general.help`/self-identity intent registered | Add a trivial `general.greeting`/self-identity guideline — this needs no AI, just a direct match, and it's embarrassing to fail. |
| "what or who is izoc?" → no match | `party.lookup` likely not wired to `resolve_entity`, or resolve_entity isn't being invoked for this phrasing | Confirm the "Entity Resolution Precondition" guideline (Phase 3.4) actually fires on *any* proper-noun-like token, not just when a `transaction.search` is already inferred. Test this phrasing explicitly as its own regression case. |
| "what we have with izoc" works, "who is izoc" doesn't | Success came from literal narration substring match, not `resolve_entity` | Confirm whether `transaction.search`/`voucher.lookup` results are coming from `resolve_entity` or from a raw `ILIKE` on narration text somewhere in the capability implementation. If the latter, that's a hidden legacy path that needs removing — it's the "whack-a-mole" heuristic your spec explicitly rejected, just relocated. |
| "who worked on this voucher?" → no match despite voucher shown last turn | Anaphora resolution guideline not grounding "this voucher" to session event history at all | This is the core acceptance test for the whole Parlant integration — if this fails, the sidecar isn't doing what it was brought in to do. Debug this first, standalone, before anything else. Also worth noting: even *with* correct anaphora resolution, "who worked on it" isn't a field your ledger tracks — correct behavior is grounding the reference and returning "I don't have that information, but here's who created it / here's the audit trail" rather than a bare not-found. Don't let this collapse into a generic fallback either way. |
| Draft for "Dog"/"last century" silently succeeds | No precondition gate (see Priority 0) | Fixed by P0. Additionally: this should surface a distinct card type — `party_not_found` or `implausible_request` — not the generic no-match fallback, so the user understands *why* it was refused. |
| Draft shows raw `4600`/`1130` instead of account names | `CardPresenterService.php` isn't joining account code → name | Separate bug, unrelated to Parlant — fix in the presenter regardless of dialogue framework outcome. |

## Priority 2 — Expand the test suite before re-declaring victory

The 42-scenario suite evidently didn't catch any of this. Add explicit regression cases for:
- Self-identity / help queries.
- Same-intent, different-phrasing entity lookups (`"who is X"` vs `"what do we have with X"`) — both must resolve via `resolve_entity`, and both must succeed/fail identically.
- Anaphora immediately following any capability response (`"this voucher"`, `"that account"`, `"him"`) — test at least 3 pronoun types.
- **Adversarial draft attempts**: unresolvable party names, implausible dates, and combinations of both — these must produce `safety_policy`/`party_not_found`, never a "Balanced ✓" card. Treat this category with the same weight as the deletion/mutation safety cases (13–15, 23–25) — arguably higher, since fabricated *creation* is easier to accidentally post than a blocked deletion is to bypass.

## What to tell the agent, in one line

*"Parlant should decide when to call a capability and how to hold a conversation. It should never be the thing standing between a user's words and a posted-looking financial artifact — that gate belongs in `alamia-360`'s capability layer, in code, and it currently isn't there. Fix the P0 gate first, then re-run the transcript above verbatim as a regression test before touching anything else."**