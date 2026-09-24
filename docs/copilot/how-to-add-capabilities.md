# Questions:A few insights/questions:

1. How do i add a new capability?? e.g. I would like to add capability for "Outstanding Receievables??" (dont code, just tell me process concisely)
2. write now the copilot understands the available capabilities only; when i send a message it doesnt understand except the ones that are defined/hardcoded into its logic
3. if i login as a different role, how does the copilot adapt/change?


## Here are concise, structured answers to your three questions:

---

### 1. How to Add a New Capability (e.g., "Outstanding Receivables")

The process has **3 simple steps**:

1. **Define the Capability in your Bridge / ServiceProvider:**
   Use the fluent API:
   ```php
   Alamia360::capability('get_outstanding_receivables')
       ->describe('Returns aged customer balances and total unpaid receivables')
       ->input(['as_of_date' => ['type' => 'string', 'required' => false]])
       ->output(['receivables' => ['type' => 'array'], 'total_outstanding' => ['type' => 'number']])
       ->withSideEffect('read')
       ->allowedFor(['human', 'ai'])
       ->handleUsing(function (array $input, $actor) {
           return app(ReportService::class)->receivables($input['as_of_date'] ?? now()->toDateString());
       });
   ```

2. **Grant the Capability to the Copilot Actor:**
   Add `'get_outstanding_receivables'` to `taliya_copilot`’s capability list via `->withCapabilities([...])`.

3. **Wire Intent / Execution in Copilot:**
   Allow the Copilot router to dispatch to `'get_outstanding_receivables'` via `Alamia360::capabilities()->execute('get_outstanding_receivables', ...)`. Because it’s an Alamia 360 capability, it automatically inherits **audit trail logging, authorization checks, and MCP tool exposure**.

---

### 2. Generalizing Intent Understanding (Beyond Hardcoded If/Else)

Right now, we used a **deterministic keyword/rule router** because it has zero latency, zero cost, and zero external API dependencies. 

To make the Copilot understand open-ended natural language, we use **LLM Tool Calling (Function Calling)**:

```
User Query ("Who owes us money this month?")
       │
       ▼
LLM (OpenAI / Claude / Gemini / Local Ollama)
+ Tools: Auto-injected from Alamia360::capabilities()->all()
       │
       ▼
LLM chooses tool: `get_outstanding_receivables` with args: `{}`
       │
       ▼
CapabilityExecutor runs tool -> Returns verified DB result
       │
       ▼
LLM synthesizes natural answer ("Customer ABC owes PKR 40,000...")
```

* **No duplication:** You do **not** write separate schemas for the AI. The `inputSchema()` and `description()` defined on the `Alamia360::capability()` are converted directly into the OpenAI/MCP tool definitions by `McpAdapter`.
* **Fallback hierarchy:** Deterministic rules run first for common queries (instant response), and unmatched queries fall through to the LLM router.

---

### 3. How the Copilot Adapts When Logging In as a Different Role

Alamia 360 enforces a strict security principle: **AI actors never have more permissions than the human user they are assisting.**

Here is how it adapts per role:

1. **Dynamic Actor Mapping (User Context):**
   When a user logs in (e.g., `accountant`, `auditor`, or `sales_rep`), the Copilot executes capabilities on behalf of that active user session:
   ```php
   $currentActor = Actor::human($user->id, $user->role)
       ->withCapabilities($user->roleCapabilities());
   ```

2. **Capability Filtering (`allowed_actor_types` & `isAuthorizedFor`):**
   * **Auditor Role:** Has read permissions (`get_financial_report`, `lookup_account`), but lacks `post_voucher`. If an Auditor asks Taliya *"Post this voucher"*, the `CapabilityExecutor` rejects it with `CapabilityAuthorizationException`. Taliya replies: *"Your current role (Auditor) is read-only and not permitted to post journal vouchers."*
   * **Accountant Role:** Has both read and write capabilities (`draft_voucher`, `post_voucher`), so action buttons (like "Post Voucher Now") are enabled.

3. **Dynamic UI Adaptation:**
   The frontend queries `GET /api/copilot/capabilities` upon login. The quick prompts and interactive cards adapt automatically based on which capabilities the current user's role is authorized to execute.
---

## Follow-up Questions: Multi-Tenancy, Audit Logging & Safety

4. **Company Context & Multi-Tenancy:** The Copilot must respect the currently selected company and only answer/act on that active company's data.
5. **Database Audit Logging:** Are AI actions logged anywhere in the database?
6. **Rollback & Safety Invariants:** What if a user accidentally makes a mistake? Is there a rollback option?

---

### 1. Why Company Context Switched in UI but Copilot Showed Main Company

There are two reasons this happened:

1. **Frontend `CopilotWidget` State Binding:**
   In `app/page.tsx`, `companyCode={currentCompany?.code}` was passed initially, but when switching companies via the switcher dropdown, if the widget’s internal state or HTTP call doesn't read the latest company code dynamically (or if the request header `X-Company-Code` isn't re-read from `localStorage`), it defaults back to `MAIN`.
2. **Backend Domain Scoping (`DomainContext`):**
   In Laravel, `DomainContext::get()` checks:
   - Request header `X-Company-Code`
   - Request body `company_code`
   - If neither is provided, it falls back to `LedgerDomain::first()` (which is `MAIN`).
   
   **To ensure strict isolation:** Every capability call must be wrapped in `DomainContext::scope($companyCode, fn() => ...)`. This guarantees that queries for accounts, reports, or vouchers hit *only* the pivot records for that exact company domain.

---

### 2. Is there Logging of AI Actions in the Database?

**Yes — every single action is recorded in the database.**

Alamia 360 has a dedicated audit ledger table: **`alamia_audit_events`**.

Every time Taliya executes any capability (e.g. `post_voucher`, `get_financial_report`), the `CapabilityExecutor` writes an immutable record containing:
* **`actor_id`**: `'taliya_copilot'` (or the logged-in user ID)
* **`actor_type`**: `'ai'`
* **`capability`**: e.g., `'post_voucher'`
* **`side_effect`**: `'write'` or `'read'`
* **`input`**: The complete JSON payload passed to the action (accounts, amounts, references)
* **`result`**: The output returned by the domain service (e.g., voucher reference, entry ID)
* **`error`**: Any failure or validation exception
* **`at`**: High-precision timestamp

In addition, the accounting core writes to **`accounting_audit_trails`**, linking the journal entry to the user and IP address.

---

### 3. What if a User Messes Up? Is there a Rollback / Reversal Option?

In double-entry accounting (and Alamia Accounts' architecture), you **never delete or silently mutate a posted journal entry** — that violates GAAP/IFRS audit compliance.

Instead, the system provides **two rollback/correction mechanisms**:

1. **Reversal Vouchers (`POST /api/vouchers/{reference}/reverse`):**
   * Alamia Accounts already has a built-in reversal engine (`VoucherService::reverseVoucher`).
   * When reversed, it automatically creates an exact offsetting journal entry (swapping all Dr/Cr lines with reference `REV-{reference}`) and marks the original voucher as reversed.
   * **Copilot Capability:** We can easily expose a `reverse_voucher` capability to Taliya. If an accidental voucher is posted, the user can say: *"Undo voucher JV-20260924-142527"* and Taliya will execute the formal reversal.

2. **Preventive Guardrail (Pre-Post Drafting):**
   * This is why Taliya uses a two-phase pattern:
     1. **`draft_voucher`** (read-only preview with validation checks)
     2. Explicit confirmation before **`post_voucher`** (write side-effect).
   * Nothing is committed to the ledger until the user reviews the accounts, amounts, and clicks the action button.