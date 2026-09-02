# Custom Voucher Builder & Dynamic Fields Manual

This document details the architecture, configuration, and operational use of the **Voucher Builder** and **Custom Voucher Types** system in Alamia Accounts.

---

## 1. Feature Purpose & Overview

In standard accounting systems, transaction entry forms are static (e.g., Date, Account, Debit, Credit, Narration). However, real-world businesses require domain-specific transaction metadata:
- **Travel & Tourism Agencies**: PNR, Ticket Numbers, Sector, Airline, Passenger Name, Passport Number, Gross Fare, Commission.
- **Logistics & Freight**: B/L Number, Container Number, Origin, Destination, Weight, Clearing Agent.
- **Trading & Manufacturing**: Gate Pass No, Delivery Challan, Batch/Lot No, Inspection Status.

Alamia Accounts provides a **dynamic Voucher Builder** that allows administrators to create new voucher types with custom fields, validation constraints, calculation formulas, and approval hierarchies without writing any code.

---

## 2. Dynamic Field Types Supported

The visual Voucher Builder supports 14 field types:

| Field Type | UI Control | Use Case in Accounting |
| :--- | :--- | :--- |
| `text` | Single-line Input | PNR, Ticket No, Invoice Ref, Container No |
| `number` | Numeric Input | Quantity, Days, Hours, Tax Percentage |
| `currency` | Formatted Money Input | Gross Fare, Service Charges, Commission |
| `date` | Calendar Picker | Due Date, Travel Date, Return Date, Expiry |
| `dropdown` | Select Component | Airline Name, Payment Channel, Branch |
| `multiselect` | Multi-tag Dropdown | Applicable Services, Tax Heads |
| `checkbox` | Boolean Switch | Tax Exempt, Advance Paid, Return Confirmed |
| `textarea` | Multi-line Textarea | Detailed Itinerary, Special Instructions |
| `computed` | Calculated Formula Field | `Gross Fare + Taxes - Commission` |
| `table` | Embedded Line Table | Passenger List, Breakdown of Flight Legs |
| `reference` | Entity Search Link | Linked Customer, Linked Vendor, Linked Invoice |
| `file` | File Upload | E-Ticket PDF, Scanned Passport, Receipt Image |
| `signature` | Digital Canvas Pad | Customer / Driver Acknowledgement |
| `location` | Geographic Picker | Departure Port, Destination Port |

---

## 3. Business & Accounting Rules

A custom voucher is not just a form — it is mapped directly into double-entry ledger journals. The builder supports 5 rule classes:

### A. Account Restriction Rules (`voucher_account_rules`)
Prevents non-accountants from posting to wrong account categories:
- **Debit Rules**: Restrict debit selections to specific groups (e.g. only `Current Assets` or `Cash/Bank`).
- **Credit Rules**: Restrict credit selections to specific groups (e.g. only `Revenue` or `Payables`).

### B. Validation Rules (`voucher_validation_rules`)
- `required`: Field cannot be left blank.
- `min_value` / `max_value`: Bounds numeric inputs.
- `regex`: Enforces formats (e.g., PNR 6 alphanumeric characters: `^[A-Z0-9]{6}$`).
- `date_range`: Ensures travel date is on or after voucher date.

### C. Auto-Calculation Formulas (`voucher_calculation_rules`)
Allows defining formulas that update fields in real time:
- Example: `net_payable = fare + taxes - (fare * commission_pct / 100)`

### D. Default Value Rules (`voucher_default_rules`)
- Auto-populates defaults based on company context or user role.

### E. Approval Rules (`voucher_approval_rules`)
- Vouchers exceeding defined monetary thresholds (e.g. > Rs. 500,000) are flagged with `pending_approval` status, requiring approval by an authorized role (`admin` or `manager`).

---

## 4. Backend Database Schema

```sql
custom_voucher_types
├── custom_voucher_fields        (name, type, required, options JSON)
├── voucher_account_rules       (side: debit/credit, account_groups JSON)
├── voucher_validation_rules    (field_name, type, value, message)
├── voucher_calculation_rules   (target_field, formula, description)
├── voucher_default_rules       (field_name, condition, default_value)
└── voucher_approval_rules      (condition, approver_role, min_amount)
```

---

## 5. How to Create a Custom Voucher (Step-by-Step)

1. Navigate to **Custom Voucher Types** -> **New Voucher Type**.
2. Enter Name (e.g. `Airline Ticket Sales`) and Prefix (e.g. `TKT`).
3. Switch to **Voucher Builder** canvas:
   - Drag **Text Field** -> Label: `PNR` -> Mark **Required**.
   - Drag **Text Field** -> Label: `Ticket Number` -> Set length to 13.
   - Drag **Dropdown** -> Label: `Airline` -> Options: `PIA, Saudia, Emirates, Qatar`.
   - Drag **Currency Field** -> Label: `Gross Fare`.
   - Drag **Currency Field** -> Label: `Agent Commission`.
4. Define Accounting Rule:
   - Debit: `Cash` or `Accounts Receivable`.
   - Credit: `Ticket Sales Revenue` and `Airline Payables`.
5. Click **Save Voucher Type**.
6. The new voucher is immediately available for transactions under the **Vouchers** menu.
