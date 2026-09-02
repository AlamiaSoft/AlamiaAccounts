# Alamia Accounts: System Architecture

This document provides a comprehensive overview of the Alamia Accounts architecture, encompassing backend logic, organization structure, and UI integration.

## 1. Core Architecture Principles

The system is designed with a **clean separation of concerns** between the accounting engine and the application host.

### A. Reusable Accounting Engine (`alamia-accounts` package)
The core logic resides in a standalone Laravel package. This ensures that the accounting rules, calculations, and reporting logic are portable across any Laravel project.
- **Service Layer**: All business logic is encapsulated in Services (e.g., `VoucherService`, `AccountService`).
- **Domain Scoping**: Every accounting entity belongs to a "Domain" (Company/Branch/Department), ensuring strict data isolation.
- **Provider Pattern**: Logic for voucher numbering, PDF template rendering, and auto-ledger posting is abstracted through specialized services.

### B. Application Host (Laravel Project)
The host application handles infrastructure and business-specific workflows:
- **Authentication**: Laravel Sanctum for API token-based auth.
- **Authorization**: Spatie Permissions for RBAC.
- **Workflows**: Approval processes, notifications (Email/SMS), and external webhooks.
- **Deliverables**: Handling file uploads (attachments, logos) and serving the frontend UI.

## 2. Organization Hierarchy (Multi-Level)

The system supports complex organizational structures using **Abivia Domains** with type classification:

| Level | Type | Description |
|-------|------|-------------|
| 0 | Company | The primary legal entity. Completely isolated from other companies. |
| 1 | Branch | Physical locations (Head Office, Region A). Can have its own accounts. |
| 1 | Department | Logical groupings (Sales, HR). Used for departmental reporting. |

### Data Isolation
- **DomainContext**: A singleton that tracks the "active" domain for the current request.
- **Automatic Scoping**: All queries are automatically scoped to the active domain via the `abivia/ledger` core.

## 3. Technology Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Core Ledger**: [abivia/ledger](https://github.com/abivia/ledger)
- **Frontend**: Next.js / React (Modern, interactive UI)
- **Database**: SQLite (optimized for "offline-first" agility)
- **API**: RESTful API with Swagger documentation (L5-Swagger)
- **Deployment**: Dockerized services (Backend & Frontend) via Cloudflare Tunnels to a Hetzner VPS.

## 4. Feature Implementation Strategy

| Feature | Logic Location | Implementation Detail |
|---------|----------------|-----------------------|
| **Vouchers** | Package | Multi-entry journal engine with validation rules. |
| **Search** | Package | Cross-domain search service for accounts and vouchers. |
| **Reports** | Package | Real-time Trial Balance, P&L, and Balance Sheet generation. |
| **Print System**| Package | PDF generation logic with customizable JSON templates. |
| **Workflow** | App | Custom approval levels and notification triggers. |

## 5. UI Integration Strategy

The **React-based Next.js UI** connects to the Laravel Backend as an independent consumer:
- **API-First**: All UI actions are driven by authenticated API requests.
- **State Management**: React Query for caching and synchronization.
- **Static Export**: The UI is designed to be statically exported into the Laravel `public/` directory for deployment on shared hosting environments if needed.

## 6. Future-Proofing & AI
- **Vector Search**: The SQLite backend is ready for `sqlite-vss` integration to enable AI-powered natural language queries on the ledger.
- **Scalability**: The architecture allows for an easy transition from SQLite to PostgreSQL/PostGIS or MySQL as the project grows beyond "offline-first" requirements.

---
*Created on: 2026-02-15*
