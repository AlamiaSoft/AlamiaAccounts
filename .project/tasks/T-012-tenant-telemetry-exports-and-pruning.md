---
id: "T-012"
title: "Tenant-Scoped Telemetry Exports & Configurable Pruning Service"
status: "paused"
priority: "low"
epic: "EP-06-multi-tenant-governance"
assigned_to: "backend"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/app/Copilot/CopilotDiagnosticsService.php"
  - "AlamiaAccounts-Backend/app/Http/Controllers/Api/CopilotController.php"
decisions:
  - "ADR-001-hybrid-knowledge"
verification: "php AlamiaAccounts-Backend/tests/copilot/verify_diagnostics_and_self_learning.php"
tags:
  - "governance"
  - "rbac"
  - "pruning"
created_at: "2026-10-07T14:10:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
pause_reason: "Paused until Kamal Express production pilot is stable"
---

## Objective
Implement multi-tenant data governance by securing diagnostic telemetry exports per tenant boundary and providing a scheduled retention/pruning artisan command (`copilot:prune`).

## Acceptance Criteria
- [ ] `GET /api/copilot/diagnostics/export` enforces `X-Company-Code` scoping
- [ ] Console command `php artisan copilot:prune --days=30` safely removes old diagnostic traces without affecting active knowledge rules
- [ ] Diagnostic verification tests pass (7/7)
