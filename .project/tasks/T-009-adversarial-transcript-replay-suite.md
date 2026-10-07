---
id: "T-009"
title: "Verbatim Adversarial Transcript Replay Regression Suite"
status: "paused"
priority: "high"
epic: "EP-03-copilot-intelligence"
assigned_to: "reviewer"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/tests/copilot/verify_copilot_intents.php"
decisions:
  - "ADR-001-hybrid-knowledge"
verification: "php AlamiaAccounts-Backend/tests/copilot/verify_copilot_intents.php"
tags:
  - "qa"
  - "regression"
  - "security"
created_at: "2026-10-07T14:10:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
pause_reason: "Paused until Kamal Express production pilot is stable"
---

## Objective
Harden Copilot dialogue against real adversarial and out-of-domain review transcripts.

## Acceptance Criteria
- [ ] Multi-turn transcript replay covering:
  1. Temporal safety refusal ("Dog Pvt Ltd / last century").
  2. Adversarial date extraction ("Year 2026 vs Rs. 50,000 amount").
  3. P&L zero-totals anomaly detection on active tenants.
  4. Stale context decay probe.
- [ ] 100% assertions green without regressions.
