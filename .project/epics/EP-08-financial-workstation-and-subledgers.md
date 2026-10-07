---
id: "EP-08-financial-workstation-and-subledgers"
title: "Tally-Style Financial Workstation, Bank Book & Subledgers"
status: "active"
priority: "high"
created_at: "2026-10-07"
updated_at: "2026-10-07"
---

# Epic: Tally-Style Financial Workstation, Bank Book & Subledgers

## Executive Summary
Transform Alamia Accounts into an intuitive, keyboard-first financial workstation reminiscent of Tally's rapid navigation. Accountants can seamlessly review ledgers, books, and subledgers, navigating rows using arrow keys and hitting `Enter` to instantly view or edit the underlying voucher.

## Core Modules & DAG Tasks
1. **`T-032`**: Backend Bank Book Query Service (`GET /api/reports/bank-book`).
2. **`T-033`**: Backend Subledger Analytics Service for AR and AP (`GET /api/subledgers/*`).
3. **`T-034`**: Frontend Reusable Tally Table Navigation Hook (`useTallyTableNavigation`).
4. **`T-029`**: Interactive General Ledger with Row Highlight & Voucher Drilldown/Edit.
5. **`T-030`**: Dedicated Bank Book UI Component with Account Switcher & Running Balance.
6. **`T-031`**: Dedicated AR & AP Subledger Views with Party Directories & Aging.
7. **`T-035`**: End-to-End Test Suite Certifying Keyboard Navigation & Subledger Drilldowns.
