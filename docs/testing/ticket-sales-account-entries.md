Q: so that means a sale of 100K on which we have to give 10K commission to a agent can put that 10K in retained earnings; right?

A: Since you are developing accounting software, you need to ensure your new airline ticket sales voucher automatically handles the underlying double-entry accounting logic behind the scenes.
When a user enters a 100K ticket sale with a 10K agent commission, your system's database engine should generate two sets of balancing ledger entries—one for the revenue and one for the expense.
Here is how you should architecture the voucher's posting engine:
## 1. The Behind-the-Scenes Ledger Postings
When this voucher is saved/posted, your system should write the following rows to your LedgerEntries table:

| Account Type | Account Name | Debit ($) | Credit ($) | Impact |
|---|---|---|---|---|
| Asset | Accounts Receivable (or Bank/Cash) | 100,000 | | Customer owes full amount |
| Revenue | Ticket Sales Revenue | | 100,000 | Total gross sales increase |
| Expense | Agent Commission Expense | 10,000 | | Business operating cost increases |
| Liability | Agent Commission Payable (or Agent Ledger) | | 10,000 | You owe the agent money |
| TOTALS | | 110,000 | 110,000 | System Balances |

## 2. How it Reaches Retained Earnings (The System Automation)
As a software developer, you shouldn't let users post anything directly to Retained Earnings via this voucher. Instead, code a Year-End Closing Routine or a dynamic Profit & Loss Engine:

   1. The engine sums up all Credit balances in Revenue accounts (100K).
   2. The engine subtracts all Debit balances in Expense accounts (10K).
   3. The net result (90K Net Profit) is automatically calculated and displayed/moved into the Retained Earnings account on the Balance Sheet interface.

## 3. Database & UI Design Best Practices for this Voucher
To make your software user-friendly for travel agents, configure the voucher's data structure like this:

* The UI Form: Only ask the user for Gross Fare (100K) and Agent Commission (10K or a 10% calculated field).
* Do not expose Retained Earnings as an option on the UI dropdown menus for transactional vouchers; it keeps your users from accidentally corrupting their balance sheets.
* Agent Sub-ledgers: Map the Commission Payable credit to the specific Agent's ID in your database so the software can track how much is owed to each individual agent.

To help tailor the database schema or logic, tell me:

* What backend language/database (e.g., SQL, PostgreSQL, MongoDB) or framework are you building this with?
* Does this voucher also need to track IATA/Airline payables (e.g., net fare to the airline vs. markup)?

I can write out the specific SQL schema or JSON payload structure for this voucher pattern.

