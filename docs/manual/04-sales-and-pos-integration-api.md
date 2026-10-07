---
id: manual-04-sales-and-pos-integration-api
title: Sales & POS Integration API Guide
module: Front-Office & External Integrations
tags: [api, pos, travel_agency, retail, sales_voucher, receipt_voucher, double_entry, integration]
api_endpoints:
  - POST /api/v1/sales
  - GET /api/v1/sales
  - GET /api/v1/sales/{id}
  - POST /api/v1/sales/{id}/approve
  - GET /api/v1/receipts/{id}/print
tables:
  - operational_sales
  - operational_sale_items
  - domain_journal_entries
  - domain_ledger_accounts
invariants:
  - "all sales transactions must authenticate with Bearer API Key"
  - "tenant scoping strictly enforced via X-Company-Code or key domain binding"
  - "staff scoping prevents cross-staff data leakage in multi-counter environments"
  - "every finalized sale automatically posts balanced Sales Voucher (SV-) and Receipt Voucher (RV-)"
---

# Sales & Front-Office POS Integration API Guide

This chapter provides a complete guide for connecting front-office operational systems (such as Travel Agency Booking Desks, Retail POS, Clinic Billing, and Service Portals) into **Alamia Accounts**.

---

## 1. Overview & Architectural Role

Front-office staff (e.g., ticket booking agents at **Kamal Express Travel**) need simple, fast interfaces to record sales, collect payments, and print client receipts without manually calculating double-entry debits and credits.

```mermaid
flowchart LR
    subgraph Frontline["Front-Office Apps (Web, Desktop, HTML POS)"]
        UI["Kamal Express POS<br/>(Single-file HTML / Mobile App)"]
    end

    subgraph Gateway["Alamia Accounts Integration API"]
        Auth["API Key Auth & Tenant Scoping"]
        SalesSvc["SalesIntegrationService"]
    end

    subgraph LedgerKernel["Alamia Double-Entry Kernel"]
        AR["Accounts Receivable (1140)<br/>(Customer Subledger)"]
        Rev["Ticket / Service Revenue (4100)"]
        Cash["Cash in Hand (1110) / Bank (1130)"]
        Vouchers["SV-YYYY-XXX & RV-YYYY-XXX"]
    end

    UI -->|POST /api/v1/sales<br/>(Customer, Items, Cash)| Gateway
    Gateway -->|Auto-orchestrates| LedgerKernel
    LedgerKernel -->|Returns Voucher Refs & Receipt URL| UI
```

---

## 2. Authentication & Tenant/Staff Scoping

### Headers
Every request to the Integration API must include:

```http
Authorization: Bearer <API_KEY_OR_TOKEN>
X-Company-Code: <TENANT_CODE>
Content-Type: application/json
Idempotency-Key: <UNIQUE_CLIENT_TX_ID>
```

### Staff-Level Data Scoping
To prevent counter staff from viewing or tampering with sales made by other employees:
- Each staff member authenticates with their individual API token or credentials.
- `GET /api/v1/sales` automatically defaults to filtering by the authenticated staff member's ID (`created_by_user_id`).
- Only authorized Managers / Lead Accountants can view company-wide aggregated sales by passing `?scope=all`.

---

## 3. Core API Endpoints

### A. Create a Sale / POS Transaction
**Endpoint**: `POST /api/v1/sales`

#### Request Payload:
```json
{
  "client_reference_id": "TKT-2026-8819",
  "issue_date": "2026-10-07",
  "due_date": "2026-10-07",
  "customer": {
    "name": "Muhammad Usman",
    "phone": "+92 300 1234567",
    "email": "usman@example.com",
    "cnic_or_ntn": "35201-1234567-1",
    "auto_create_subledger": true
  },
  "line_items": [
    {
      "description": "Air Ticket: LHR -> DXB (Emirates EK-623)",
      "quantity": 1,
      "unit_price": 100000.00,
      "revenue_account_code": "4100",
      "tax_rate_percent": 0.00,
      "metadata": {
        "pnr": "6XJ8KL",
        "ticket_number": "176-2490182736",
        "route": "LHR-DXB",
        "passenger": "Muhammad Usman",
        "flight_date": "2026-10-15"
      }
    }
  ],
  "payments": [
    {
      "method": "cash",
      "amount": 100000.00,
      "deposit_account_code": "1110",
      "reference_note": "Cash received at Counter 1"
    }
  ],
  "workflow": {
    "mode": "instant_post",
    "notes": "Walk-in ticket purchase"
  }
}
```

#### Success Response (`HTTP 201 Created`):
```json
{
  "success": true,
  "data": {
    "sale_id": "SALE-2026-00421",
    "status": "posted",
    "client_reference_id": "TKT-2026-8819",
    "customer": {
      "name": "Muhammad Usman",
      "subledger_account": "1140"
    },
    "vouchers": {
      "sales_voucher": "SV-2026-00421",
      "receipt_voucher": "RV-2026-00421"
    },
    "totals": {
      "gross_amount": 100000.00,
      "tax_amount": 0.00,
      "net_amount": 100000.00,
      "paid_amount": 100000.00,
      "balance_due": 0.00
    },
    "receipt": {
      "receipt_number": "RCP-2026-00421",
      "print_url": "/api/v1/receipts/RCP-2026-00421/print",
      "qr_payload": "ALAMIA|KAMAL_EXPRESS|SV-2026-00421|100000|2026-10-07"
    }
  }
}
```

---

### B. List Sales (Scoped to Active Staff)
**Endpoint**: `GET /api/v1/sales`

**Query Parameters**:
- `page` (default: 1)
- `per_page` (default: 25)
- `status` (`posted`, `pending_approval`, `draft`)
- `from_date` / `to_date`
- `scope` (`mine` [default] or `all` [requires manager role])

---

### C. Approve Staged Sale (Manager / Accountant)
**Endpoint**: `POST /api/v1/sales/{id}/approve`

When sales require approval before ledger posting (e.g. credit sales or high-value transactions):
- The sale is created with `"workflow": { "mode": "pending_approval" }`.
- The manager reviews and calls `/approve`, which commits the `SV-` and `RV-` vouchers to the permanent double-entry ledger.

---

## 4. Connecting a Single-File HTML / POS Client

Frontline staff can use a standalone HTML file that connects securely to Alamia Accounts via JavaScript `fetch()`:

```javascript
// Sample Single-File POS Client Submission
async function submitSale(saleData) {
  const apiKey = localStorage.getItem('alamia_api_key');
  const companyCode = localStorage.getItem('alamia_company_code') || 'KAMAL_EXPRESS';
  const baseURL = 'http://localhost:8000/api/v1';

  const response = await fetch(`${baseURL}/sales`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`,
      'X-Company-Code': companyCode,
      'Idempotency-Key': 'tx_' + Date.now(),
    },
    body: JSON.stringify(saleData)
  });

  const result = await response.json();
  if (result.success) {
    // Open printable receipt immediately
    window.open(result.data.receipt.print_url, '_blank');
  } else {
    alert('Error recording sale: ' + result.message);
  }
}
```

---

## 5. Double-Entry Accounting Invariants Guaranteed

1. **Automatic Balanced Postings**:
   - `Sales Voucher`: `Dr 1140 (AR)` | `Cr 4100 (Revenue)`
   - `Receipt Voucher`: `Dr 1110 (Cash)` | `Cr 1140 (AR)`
2. **Immutable Audit History**:
   - Sales cannot be deleted; if cancelled, a compensating reversal voucher (`REV-`) is generated.
3. **Idempotency**:
   - Resending a request with the same `Idempotency-Key` returns the original voucher without double-booking.
