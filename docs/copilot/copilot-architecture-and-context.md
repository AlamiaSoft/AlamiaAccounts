# AI Copilot Architecture & Context Guide

This document describes how to configure, index, and integrate the **AccounTech AI Copilot** in future phases.

---

## 1. Copilot System Role & Mission

The AI Copilot operates as an intelligent accounting co-pilot embedded within the application. Its mission is to:
1. **Assist Users with Accounting Queries**:
   - Clarify the difference between category folders and posting accounts.
   - Advise on proper debit/credit account selection according to standard GAAP/IFRS rules.
2. **Automate Voucher Preparation**:
   - Ingest conversational requests (e.g., *"We paid Rs. 45,000 for office stationery via Meezan Bank"*) and draft balanced double-entry vouchers with exact accounts.
3. **Analyze Financial Statements**:
   - Provide narrative insights on Profit & Loss statements, cash flow trends, and Balance Sheet health.
4. **Validate Business Invariants**:
   - Pre-check that vouchers balance (`Dr === Cr`) and that no detail line targets a non-posting category account before sending the payload to the backend API.

---

## 2. Ingestion & RAG Indexing Strategy

When setting up embeddings / vector search for the Copilot:

| Directory | Format | Ingestion Target | Purpose |
| :--- | :--- | :--- | :--- |
| `docs/copilot/manifest.json` | JSON | Direct System Prompt / Tool Schema | Rapid routing of user intents to API endpoints and validation constraints. |
| `docs/copilot/accounting-rules.json` | JSON | Structured Tool Context | Formalized code ranges, debit/credit normal balance rules, and invariants. |
| `docs/manual/*.md` | Markdown | Vector Store / RAG Chunks | Semantic retrieval for conceptual explanations, walkthroughs, and troubleshooting. |

---

## 3. Standard Copilot Toolset (Function Calling Schemas)

When equipping the Copilot LLM with tools, configure the following functions:

### Tool 1: `lookup_account(query: string, company_code: string)`
- **Action**: Searches the active company's chart of accounts for matching accounts.
- **Returns**: Account code, name, category flag (`is_folder`), normal balance side, and current balance.
- **Constraint Check**: If the user wants to post a transaction, the Copilot must filter for `category: false`.

### Tool 2: `draft_voucher(type: string, description: string, details: Array<{account_code, debit, credit}>)`
- **Action**: Constructs a validated voucher draft in the UI.
- **Pre-execution Check**:
  - `sum(details.debit) === sum(details.credit)`
  - None of `details.account_code` has `category === true`
- **Safety Policy**: Requires explicit user confirmation before calling `POST /api/vouchers`.

### Tool 3: `query_financial_report(report_type: string, period: string, company_code: string)`
- **Action**: Calls `GET /api/reports/{report_type}` and synthesizes high-level summary cards or variance analysis for the user.

---

## 4. Guardrails & Safety Policy

1. **Category Posting Rejection**: The Copilot must never recommend or draft a transaction targeting a parent category (e.g., `1000`, `1100`, `1120`, `2000`, `4000`, `5000`). If a user asks to *"Pay from Bank Accounts"*, the Copilot must prompt: *"Bank Accounts (1120) is a category folder. Would you like to pay from 1130 Meezan Bank or 1135 Bank Alfalah?"*
2. **Multi-Company Scoping**: The Copilot must always pass `X-Company-Code` header matching the user's active tenant selection.
3. **Balance Invariant**: The Copilot must refuse to execute an unbalanced voucher.
