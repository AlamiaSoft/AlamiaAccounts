---
id: "T-008"
title: "Structured Conversation Context Binding & Expiry Reset Rule"
status: "paused"
priority: "medium"
epic: "EP-03-copilot-intelligence"
assigned_to: "backend"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/app/Copilot/CopilotService.php"
decisions:
  - "ADR-001-hybrid-knowledge"
verification: "php AlamiaAccounts-Backend/tests/copilot/verify_copilot_intents.php"
tags:
  - "copilot"
  - "context-decay"
created_at: "2026-10-07T14:10:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
pause_reason: "Paused until Kamal Express production pilot is stable"
---

## Objective
Prevent "context stickiness" where prior turn entities (e.g. `active_voucher = SV-2026-112`) incorrectly bind to later unrelated prompts (e.g. "what's the balance of Meezan Bank?").

## Acceptance Criteria
- [ ] Explicit context reset when a new entity is resolved.
- [ ] Context decay after N=2 turns without entity reference.
- [ ] Stale context probe test passing in test suite.
