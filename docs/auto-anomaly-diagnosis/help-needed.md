# Expert Consultation Prompt: Accounting Diagnostic & Forensic Anomaly Engine

---

```markdown
### Context & Architectural Baseline

We are developing an institutional double-entry accounting engine (Alamia Accounts) built on an Abivia Ledger core. 

#### 1. Accounting & Reporting Architecture
- **Double-Entry Invariant**: Every posted journal entry must satisfy $\sum \text{Debits} \equiv \sum \text{Credits}$. Transactions are strictly immutable (corrections require compensating reversal vouchers).
- **Hierarchical Chart of Accounts**: Structured tree with non-posting parent categories (`category: true`) and posting leaf accounts (`category: false`), categorized under `ASSET`, `LIABILITY`, `EQUITY`, `REVENUE`, and `EXPENSE`.
- **Dynamic Balance Sheet Reporting**: Dynamic real-time calculation where current period Net Profit ($\text{Revenue} - \text{Expenses}$) is dynamically calculated and rolled into Retained Earnings / Equity on the fly, alongside opening balance positions and historical retained earnings.
- **Tenant Partitioning**: Multi-tenant database partitioned by `domainUuid` with domain-scoped ledgers.

#### 2. Current Diagnostic Service (`AccountingDiagnosticService.php`)
When a Balance Sheet is generated, the system audits mathematical and structural integrity:
- **Discrepancy Formula**: $\text{Discrepancy} = \left| \text{Total Assets} - (\text{Total Liabilities} + \text{Total Equity}) \right|$
- **Current Forensic Scan Vectors**:
  1. **Unclassified / Orphan Leaf Accounts**: Accounts with non-zero balances that lack parent mapping or class tagging and are omitted from the BS tree.
  2. **Corrupted / Single-Legged Vouchers**: Journal vouchers where $\sum Dr \neq \sum Cr$.
  3. **Retained Earnings Distortion**: Direct manual journal entries into Retained Earnings (`5200`) duplicating dynamic net profit, or future-dated P&L transactions past statement cutoff.
  4. **Uncleared Suspense / Clearing Balances**: Non-zero balances in suspense (`9999`, `9000`) accounts.
  5. **Normal Balance Inversions**: Abnormal signs (e.g., negative net-credit balances on active asset accounts).
- **Discrepancy Reconciliation Engine**: Each finding's `impact_amount` is reconciled against the discrepancy:
  - `EXPLAINS_DISCREPANCY` (matches 100% of discrepancy)
  - `PARTIALLY_EXPLAINS` (fraction of discrepancy)
  - `UNRELATED_SIGNAL` (operational finding that preserves double-entry, e.g. suspense balance or sign inversion)

---

### Request for Accounting Expert Guidance

As a Senior CPA / ERP Accounting Architect, please review this design and advise on:

1. **Missing Imbalance Vectors**:
   - What other specific accounting or system conditions cause Balance Sheet imbalances in dynamic ERP reporting (e.g., multi-currency exchange rate translation rounding, prior-year opening balance rollover mismatches, multi-period cross-fiscal cutoff gaps, contra-account double-counting)?

2. **Compound Discrepancy Reconciliation Algorithms**:
   - When a Balance Sheet discrepancy is caused by a combination of multiple simultaneous errors (e.g., an unclassified asset of Rs. 30,000 + an unbalanced voucher of Rs. 50,000 explaining an Rs. 80,000 total discrepancy), what is the most mathematically rigorous approach to partition, rank, and prove compound causality without combinatorial explosion?

3. **Broader Forensic Accounting Anomalies (Beyond BS Imbalance)**:
   - What high-value forensic anomalies should our diagnostic engine detect even on a mathematically balanced ledger (e.g., zombie accounts, circular inter-account postings, revenue/expense timing anomalies, excessive adjustments, unlinked control vs. subledger reconciliations)?

4. **Actionable Remediation Blueprints**:
   - For each identified anomaly vector, what exact double-entry remediation recipe (compensating journal entries, period closing steps, or Chart of Accounts re-parenting) should our AI Copilot generate for the accountant's review and approval?
```
