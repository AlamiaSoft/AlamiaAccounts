---
id: "T-013"
title: "Kamal Express Pilot Chart of Accounts & Airline Subledger Seeding"
status: "done"
priority: "high"
epic: "EP-07-kamal-express-pilot"
assigned_to: "backend"
depends_on:
blocks:
relevant_files:
  - "AlamiaAccounts-Backend/database/seeders/DatabaseSeeder.php"
  - "AlamiaAccounts-Backend/packages/AlamiaSoft/alamia-accounts/src/Services/CompanyService.php"
decisions:
  - "ADR-002-pos-integration-contract"
verification: "php AlamiaAccounts-Backend/tests/integration/verify_sales_integration_api.php"
tags:
  - "seeder"
  - "onboarding"
  - "pilot"
created_at: "2026-10-07T14:15:00Z"
updated_at: "2026-10-07"
decision_refs:
files:
claimed_by: "agent"
started_at: "2026-10-07T09:17:28.594Z"
start_git_commit: "e9a94d4"
start_git_branch: "main"
completed_at: "2026-10-07T09:20:21.423Z"
completed_git_commit: "e9a94d4"
verification_evidence: "KamalExpressPilotSeeder executed successfully. Subledgers for PIA, Emirates, Airblue, Fly Jinnah, Saudia, HBL created for KAMAL_EXPRESS tenant."
---

## Objective
Establish the institutional Chart of Accounts and subledgers for the Kamal Express Travel Agency tenant (`KEH` / `KAMAL_EXPRESS`).

## Acceptance Criteria
- [ ] Dedicated company tenant `KAMAL_EXPRESS` (Kamal Express Travel & Tours)
- [ ] Subledgers for Airline / Supplier Payables: `2110: PIA`, `2111: Emirates`, `2112: Airblue`, `2113: Fly Jinnah`
- [ ] Subledgers for Bank Accounts: `1120: Meezan Bank`, `1121: Habib Bank Limited`
- [ ] Revenue Accounts: `4100: Air Ticket Sales`, `4200: Umrah Packages`, `4300: Visa Services`, `4400: Hotel Bookings`
- [ ] Opening balances verified and double-entry balanced ($Dr = Cr$)
