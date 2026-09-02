# Alamia AI Copilot ("Taliya") & MCP Architecture Guide

This document describes how to configure, index, and integrate **Taliya** (the Alamia AI Copilot) and the **Alamia Accounts MCP Server** for AI dev agents, Claude Desktop, OpenAI ChatGPT, and other LLM clients.

---

## 1. Copilot System Role & Identity ("Taliya")

**Taliya** is the intelligent accounting assistant and co-pilot embedded within Alamia Accounts. Her mission is to:
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

1. **Category Posting Rejection**: Taliya must never recommend or draft a transaction targeting a parent category (e.g., `1000`, `1100`, `1120`, `2000`, `4000`, `5000`). If a user asks to *"Pay from Bank Accounts"*, Taliya must prompt: *"Bank Accounts (1120) is a category folder. Would you like to pay from 1130 Meezan Bank or 1135 Bank Alfalah?"*
2. **Multi-Company Scoping**: Taliya must always pass `X-Company-Code` header matching the user's active tenant selection.
3. **Balance Invariant**: Taliya must refuse to execute an unbalanced voucher.

---

## 5. Alamia Accounts MCP Server (Model Context Protocol)

The **Alamia Accounts MCP Server** exposes the complete double-entry accounting engine to external LLMs (Claude Desktop, OpenAI ChatGPT, Cursor, Windsurf, and autonomous developer agents).

### Server Overview
- **Protocol**: JSON-RPC 2.0 over `stdio` or Server-Sent Events (`sse`).
- **Authentication**: Bearer API token or local secret scoped to permitted company domains.
- **Tenant Scope**: Configurable default company code (`MAIN`, `KAMAL`, etc.) with dynamic per-call overrides.

### Core MCP Tools Exposed:
1. `list_accounts(company_code?, parent_code?, category_only?)`: Returns hierarchical chart of accounts and live balances.
2. `create_account(code, name, parent_code, category, debit, credit, company_code?)`: Adds a category folder or leaf posting account.
3. `get_account_statement(account_code, from_date, to_date, company_code?)`: Returns the general ledger running balance for any account.
4. `post_voucher(reference, date, description, details: Array<{account, debit, credit}>, company_code?)`: Validates double-entry invariants and posts a verified journal voucher.
5. `get_financial_statements(report_type: 'trial-balance' | 'profit-loss' | 'balance-sheet', date_or_period, company_code?)`: Returns mathematical reports.
6. `manage_companies(action: 'list' | 'create' | 'switch', data?)`: Creates new tenant environments and auto-initializes the Chart of Accounts template.

