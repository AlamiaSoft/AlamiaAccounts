---
id: "T-048"
title: "Thermal Receipt Blade Template Refactor & Print URL Normalization"
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
started_at: "2026-10-08T11:51:02.555Z"
start_git_commit: "7e70fa2"
start_git_branch: "main"
completed_at: "2026-10-08T11:51:08.526Z"
completed_git_commit: "7e70fa2"
verification_evidence: "Extracted thermal receipt heredoc to dedicated Blade view resources/views/receipts/thermal.blade.php, fixed duplicate /api/api/ prefix in frontend URL opener. HTTP GET /api/v1/receipts/{id}/print returns HTTP 200."
---

## Objective
Thermal Receipt Blade Template Refactor & Print URL Normalization

## Acceptance Criteria
- [ ] Implement required functionality
- [ ] Run verification tests
