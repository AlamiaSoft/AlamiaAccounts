---
id: "T-047"
title: "Front-Office POS Sales Amount Serialization & Daybook Reset Synchronization"
status: "done"
priority: "high"
epic: ""
assigned_to: "agent"
depends_on:
blocks:
relevant_files:
verification: ""
created_at: "2026-10-08"
updated_at: "2026-10-08"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-08T11:50:41.725Z"
start_git_commit: "7e70fa2"
start_git_branch: "main"
completed_at: "2026-10-08T11:50:47.897Z"
completed_git_commit: "7e70fa2"
verification_evidence: "Added total_amount appends attribute and accessor on OperationalSale, updated pos-sales-approval table/KPI fallbacks, and synchronized clearAllTransactions to purge POS sales and checkpoints. Passed verify_sales_integration_api.php (11/11)."
---

## Objective
Front-Office POS Sales Amount Serialization & Daybook Reset Synchronization

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
