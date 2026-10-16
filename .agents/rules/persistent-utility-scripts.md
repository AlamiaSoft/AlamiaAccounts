---
trigger: always_on
description: Mandatory rule requiring all operational, diagnostic, and API utility scripts to be persisted in repository directories rather than generated on the fly.
---

# Persistent Utility Scripts & Tooling Rule

## Directive
When executing operational, diagnostic, API (e.g., GitHub, database, external services), or ledger inspection tasks:

1. **NEVER generate ad-hoc, throwaway scripts** in ephemeral scratch directories or inline in shell commands.
2. **ALWAYS persist reusable scripts in the repository under [`scripts/`](file:///e:/Alamia/AlamiaAccounts/scripts)** organized by domain:
   - GitHub & PR Management: [`scripts/github/`](file:///e:/Alamia/AlamiaAccounts/scripts/github) (e.g., `gh_tool.py`)
   - Project Management & State: [`scripts/pm.js`](file:///e:/Alamia/AlamiaAccounts/scripts/pm.js)
   - Architecture & Quality Audits: [`scripts/verify-architecture.js`](file:///e:/Alamia/AlamiaAccounts/scripts/verify-architecture.js)
   - AI & Copilot Rules: [`scripts/ai/`](file:///e:/Alamia/AlamiaAccounts/scripts/ai)
3. **Make scripts modular and parameterized**:
   - Accept arguments (e.g. PR numbers, issue numbers, input files) rather than hardcoding task-specific identifiers.
   - Use pre-built scripts for repetitive workflows across all turns and sessions.
4. When new operational automation is needed, build it directly in `scripts/<domain>/` so it remains permanently available.
