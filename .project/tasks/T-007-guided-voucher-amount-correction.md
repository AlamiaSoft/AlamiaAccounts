---
id: "T-007"
title: "Guided Voucher Amount Correction Workflow (voucher.correct_amount)"
status: "paused"
priority: "low"
epic: "EP-03-copilot-intelligence"
assigned_to: "backend"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/app/Copilot/CopilotService.php"
  - "AlamiaAccounts-Backend/app/Copilot/GuidanceKnowledgeService.php"
  - "AlamiaAccounts-Frontend/components/copilot-widget.tsx"
decisions:
  - "ADR-001-hybrid-knowledge"
verification: "php AlamiaAccounts-Backend/tests/copilot/verify_copilot_intents.php"
tags:
  - "copilot"
  - "dialogue"
  - "reversal"
created_at: "2026-10-07T14:10:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
pause_reason: "Paused until Kamal Express production pilot is stable"
---

## Objective
Enable a seamless guided workflow when a user attempts to edit a posted voucher. Rather than a dead-end refusal ("vouchers are immutable"), Copilot must guide the user with a pre-filled reversal (`REV-`) + adjusting voucher proposal (`voucher.correct_amount`).

## Acceptance Criteria
- [ ] Grounding of anaphora: When immutability refusal fires, follow-ups like "how do I fix that" or "correct amount is 50,000" route into `voucher.correct_amount`.
- [ ] Deterministic amount extraction: Currency/amount tokens are verified via regex before drafting replacement entry.
- [ ] Safe reversal pointer: Generates clickable action to execute reversal and post compensating entry.
- [ ] Verification test suite passes with 0 regressions.
