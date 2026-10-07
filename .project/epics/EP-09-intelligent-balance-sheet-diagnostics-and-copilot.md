# Epic: EP-09 Intelligent Balance Sheet Diagnostics & AI Copilot Root-Cause Analysis

## Executive Summary
When a Balance Sheet is not in equilibrium (Assets != Liabilities + Equity), accountants need instant, automated root-cause detection rather than manual line-by-line inspection. This epic introduces an institutional Multi-Vector Anomaly Diagnostics Engine in the backend, deep integration with Taliya AI Copilot for conversational explanation & resolution, and an interactive diagnostics dashboard within the Balance Sheet UI.

## Scope & Key Capabilities
1. **Multi-Vector Anomaly Diagnostics Engine**:
   - Unclassified / orphan ledger accounts missing class hierarchy.
   - Unbalanced single-legged journal vouchers or corrupted entry lines.
   - Net Profit / Retained Earnings date cutoff asymmetries.
   - Initial compound opening balance gaps or suspense balances.
   - Direct postings to Retained Earnings without year-end closing journals.
2. **Taliya AI Copilot Root-Cause Capability**:
   - Conversational capability `diagnostics.balance_sheet_imbalance`.
   - Natural language root-cause explanation and interactive anomaly cards.
   - Guided step-by-step resolution and voucher correction drafting.
3. **Interactive UI in Financial Reports**:
   - "Auto-Diagnose Imbalance" inline expandable panel.
   - "Ask Taliya to Explain & Fix" 1-click Copilot launcher.
   - Direct navigation links to offending vouchers and accounts.
4. **Automated E2E Certification**:
   - Playwright test suite `tests/e2e/12-balance-sheet-diagnostics-and-copilot.test.js`.
