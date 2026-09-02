# Alamia Accounts Backend: AI Knowledge Base Master Index

Welcome to the canonical AI knowledge base for **AlamiaAccounts-Backend**. This repository contains the Laravel 11 host application and the core reusable accounting package `alamiasoft/alamia-accounts`.

## The AI Bootstrap Read Order
If you are an AI Agent entering a fresh conversation in this repository, you **must** read the following documents in order before inspecting the source code:

1. `.ai/README.md` (Master Index - You are here)
2. `.ai/transient/sprint/00-current-state.md` (Current sprint focus, status matrix)
3. `.ai/permanent/architecture/01-system-architecture.md` (Architecture, invariants, domain scoping)
4. `.ai/indexes/repository.md` (Mapping backend concepts, controllers, services to files)
5. `.ai/permanent/standards/01-coding-standards.md` (PHP, PSR-12, Laravel 11, domain rules)

---

## Directory Structure

### Permanent Knowledge (Lives for Years)
* **`permanent/architecture/`**: High-level design intent, domain isolation invariants, and system boundaries.
* **`permanent/adr/`**: Architecture Decision Records index and decisions.
* **`permanent/glossary/`**: The single source of truth for accounting & backend terminology.
* **`permanent/standards/`**: Backend coding conventions and testing standards.

### Transient Knowledge (Lives for Days)
* **`transient/sprint/`**: The current sprint's focus and objectives.
* **`transient/handoffs/`**: Session handoffs summarizing the immediate delta between chats.
* **`transient/backlog/`**: Pending backend tasks.
* **`transient/repository-health.md`**: Tracking architecture drift, documentation coverage, and missing docs.

### History & Meta
* **`history/`**: Architecture timeline.
* **`indexes/`**: 
  * `repository.md`: Maps backend concepts to source code directories, models, controllers, and services.
  * `dependency-map.md`: Mermaid graph of backend components.
* **`lessons/`**: Operational knowledge, debugging outcomes, and failed experiments.
