Read it. Two corrections to the doc, then the recommendation against your spec.

## Correcting the candidate framing

**Candidate 1 (LLMRouter)** — the doc's stated strength ("designed for multi-turn dialogue routing with trained round-aware representations") is a mischaracterization. LLMRouter's "multi-turn" routers use conversation history only to pick *which LLM* answers next — cost/quality optimization across model choices. They do not rewrite queries, track slots, or resolve entities. It cannot fulfill any row in your Evaluation Criteria table (#1, #4, #5 especially). Rule it out — this isn't a fit gap, it's a different problem domain entirely.

**Candidate 2 ("ai-assistant-framework")** — I can't find a real repo matching that description. The closest name match (`jamesou/ai-assistant-framework`) is a minimal, low-activity personal project with a generic "memory support" bullet point — nothing evidencing anaphora resolution, alias graphs, or contextual expansion as described. Worth asking your dev agent for the exact repo URL it evaluated before spending more time on it; as given, it doesn't check out.

## Parlant against your actual spec

| Your requirement | Parlant fit |
|---|---|
| Sidecar service, Laravel-callable | Runs as a standalone Python server (`p.Server`) exposing a local REST/WS API (default `localhost:8800`) — Laravel calls it over HTTP like any microservice. Clean fit for your architecture diagram. |
| Local LLM (qwen3.5:4b via Ollama) | Native support — `pip install parlant[ollama]`, no mandatory cloud dependency. Confirmed. |
| Anaphora/deixis (Failure Mode 1) | Parlant sessions are an ordered **event timeline** (all messages, tool calls, results), not stateless turns — the model resolving "who is he?" has the actual prior entity in its context window by construction, not via a hand-written rewrite rule. |
| Conversational repair (Failure Mode 2) | This is exactly what **Guidelines** (condition→action) and **Journeys** are for — a guideline can fire on negation+correction patterns and redirect the active journey step, instead of you hand-coding "no, not that one" detection. |
| Deterministic safety override (`safety_flag`) | Strong fit — Guidelines are enforced pre-generation, not hoped-for via prompt. A guideline like "if action implies deleting/mutating a posted voucher → refuse and return safety_policy" runs as a hard rule, matching your Section 3/5 invariants. |
| Semantic capability contract (your JSON schema) | Map each row of your capability table (`party.lookup`, `voucher.reverse`, etc.) directly to a Parlant `@p.tool`-decorated function with typed args. Journeys/guidelines decide *when* a tool fires; the tool itself talks to your PHP backend or Postgres. This preserves your dispatcher separation cleanly. |
| **Entity/alias normalization (Failure Mode 3)** | **Gap.** Parlant's Glossary handles static domain terminology, not fuzzy real-world name variants against a live ledger. You still need a small dedicated resolver — e.g., a Postgres `pg_trgm`/fuzzy-match or embedding-based lookup against `domain_contacts`/`domain_ledger_accounts`, exposed as a `resolve_entity` tool that guidelines call before `party.lookup`/`transaction.search`. This is a scoped ~1-2 week build, not a framework problem. |

## Recommendation

- Drop LLMRouter and the unverified "ai-assistant-framework" candidate.
- Adopt **Parlant** as the dialogue/routing layer — it covers criteria #1–#4 of your evaluation table directly and is the only candidate discussed so far actually built for this problem class (event-timeline sessions, guideline-based dialogue control, tool-based capability dispatch).
- Budget a **separate, small entity-resolution service** (criterion #5) — this is real net-new work regardless of which framework you pick; no OSS framework ships accounting-ledger-aware alias resolution out of the box.
- Validate against your 42-scenario contract suite early: build 3-4 of the trickiest scenarios (Failure Modes 1–2) as a Parlant prototype before committing, since your suite is the real acceptance bar.