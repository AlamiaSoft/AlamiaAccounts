# Alamia Accounts: Project Consolidation & Architecture Plan

This document serves as the single source of truth for the migration and consolidation of the Alamia Accounts project.

## Project Overview
A robust accounting system built with a "reusable package" philosophy and an "offline-first" vision for performance and agility.

- **Backend**: Laravel 11 + `abivia/ledger` + `alamiasoft/alamia-accounts` package.
- **Frontend**: Next.js / React UI (`accounting-app-ui-multico-enhanced`).
- **Database**: SQLite (Initial) with future path for Vector support.
- **Deployment**: Hetzner VPS (Docker + Portainer + Cloudflare Tunnels).

## Directory Structure (Planned)
```text
AlamiaAccounts/
├── backend/                  # Laravel Host Application
│   └── packages/             # Local package developmental workspace
│       └── AlamiaSoft/       # Package source (alamia-accounts)
└── frontend/                 # Next.js Application
```

## Architectural Decisions

### 1. Reusable Backend Package
To ensure the accounting logic is truly portable, all API controllers, routes, and business services are being moved from the Laravel application layer into the `alamiasoft/alamia-accounts` package. 
- **Package Path**: `backend/packages/AlamiaSoft/alamia-accounts`
- **Goal**: Enable any Laravel project to "plug-and-play" the accounting module by simply adding the package and a few lines of configuration.

### 2. Database & AI Strategy
- **SQLite**: Chosen for its "local-first" speed and zero-latency file-based operations. Fits the "offline-first" experience vision.
- **Vector Support**: We will explore `sqlite-vss` for embedding storage (useful for AI-driven ledger analysis/search). If scale requires it, we will migrate to PostgreSQL (pgvector) or MySQL.

### 3. Deployment Flow
- **Domains**: 
    - UI: `alamia-accounts.alamiasoft.com`
    - API: `alamia-accounts-backend.alamiasoft.com`
- **Connectivity**: Cloudflare Tunnels will bridge the local Docker services on the Hetzner VPS to the public domains.

## Immediate Next Steps
1. **Initialize Backend**: `composer create-project laravel/laravel backend` inside this folder.
2. **Link Package**: Add a local repository path in `backend/composer.json` pointing to `packages/AlamiaSoft/alamia-accounts`.
3. **Refactor Controllers**: Move existing API controllers from `abivia-tests` into the package's `src/Http/Controllers` directory and Register via `ServiceProvider`.
4. **Setup Frontend**: Move the Next.js code to `frontend/` and update environmental variables to match the new backend domain.

---
*Created on: 2026-02-15*
