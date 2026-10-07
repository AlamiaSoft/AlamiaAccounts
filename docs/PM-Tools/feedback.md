Yes — **the direction is fundamentally right, but I would change the architecture before implementing it.** The strongest idea here is not “another PM tool”; it is **a version-controlled project state machine that both humans and agents can operate against**.

### My verdict

| Part                                           | Verdict                              |
| ---------------------------------------------- | ------------------------------------ |
| `.project/` as repo-local source of truth      | ✅ Strong                             |
| Markdown + YAML frontmatter                    | ✅ Good, especially for humans + LLMs |
| Dependency DAG                                 | ✅ Essential                          |
| CLI for agents                                 | ✅ Essential                          |
| Web Kanban                                     | ✅ Useful, but secondary              |
| `pm next`                                      | ⭐ Core capability                    |
| `pm finish` + verification evidence            | ⭐ Excellent                          |
| ADRs in same system                            | ✅ Yes                                |
| Git-native/offline                             | ⭐ Major advantage                    |
| Global reusable CLI/skill                      | ✅ Yes                                |
| Automatically telling every agent to `pm next` | ⚠️ Too simplistic                    |
| Building a PM UI early                         | ❌ Don't                              |

### The key correction

Don't think of this as:

> **PM tool stored in Git**

Think of it as:

> **Project execution protocol for humans + AI agents, with Git as persistence.**

That distinction matters.

The `.project/` directory should become the **canonical project state**, while Git remains the canonical history.

I'd make the architecture:

```text
                    ┌──────────────────────────┐
                    │       .project/           │
                    │                           │
                    │ project.yaml              │
                    │ epics/                    │
                    │ tasks/                    │
                    │ decisions/                │
                    │ workflows/                │
                    │ agents/                   │
                    │ context/                  │
                    └────────────┬─────────────┘
                                 │
                     ┌───────────▼───────────┐
                     │     PM ENGINE         │
                     │ schema + DAG + rules  │
                     └──────┬─────────┬──────┘
                            │         │
                 ┌──────────▼──┐   ┌──▼──────────┐
                 │ Human CLI   │   │ AI Agents   │
                 │             │   │             │
                 │ status      │   │ next        │
                 │ board       │   │ claim       │
                 │ edit        │   │ execute     │
                 └─────────────┘   │ verify      │
                                   │ finish      │
                                   └─────────────┘
                                          │
                                          ▼
                                     Git commits
```

## One major thing missing: **Agent execution state**

Your task status alone isn't enough.

An agent needs to know:

```yaml
status: ready
assigned_to: agent
claimed_by: spark
attempt: 2
started_at:
branch:
commit:
verification:
blocked_reason:
```

Because eventually you'll have:

**Human → Agent A → Agent B → Human review → Agent C**

and the PM system needs to preserve the handoff.

I'd therefore introduce explicit states:

```text
BACKLOG
   ↓
READY
   ↓
CLAIMED
   ↓
IN_PROGRESS
   ↓
REVIEW
   ↓
VERIFIED
   ↓
DONE
```

with:

```text
BLOCKED
ABANDONED
```

as exceptional states.

---

# The really interesting part

I think you're slightly underestimating what this could become.

The killer primitive isn't `pm board`.

It's:

```bash
pm next
```

Imagine an agent starting with **zero conversation context**.

It executes:

```bash
pm next --agent coding
```

and gets:

```text
TASK T-143
Implement SalePostingService

WHY NOW
- EP-12 is active
- Dependencies satisfied
- Priority: HIGH

CONTEXT
- ADR-017
- T-139
- T-141

OBJECTIVE
...

ACCEPTANCE
...

VERIFICATION
php artisan test --filter=SalePosting

HANDOFF
When complete:
  pm finish T-143 --evidence="..."
```

That is dramatically better than dumping:

> README + ROADMAP + AGENTS.md + 17 markdown files + Git history

into an LLM context window.

**The PM engine becomes a context compiler.**

That's potentially the real product.

---

# I would also change your task model

Don't make `dependencies` the only relationship.

You want something closer to:

```yaml
id: T-143
type: implementation

epic: EP-12

depends_on:
  - T-139

blocks:
  - T-151

related:
  - T-141

decision_refs:
  - ADR-017

files:
  - app/Services/SalePostingService.php

verification:
  command: php artisan test --filter=SalePosting

agent:
  capability: backend
  model: coding
```

This lets the engine construct **task-specific context**.

---

# Another important distinction: task ≠ work session

Don't put transient agent reasoning into the task itself.

Have:

```text
.project/
├── tasks/
│   └── T-143.md
├── runs/
│   ├── T-143/
│   │   ├── 001.json
│   │   └── 002.json
│   └── ...
```

Task = durable specification.

Run = ephemeral execution history.

That will become extremely valuable when debugging agent failures.

---

# And don't make `.project/` too big

This is where the original proposal could accidentally recreate the exact problem it's trying to solve.

Agents should **never recursively read `.project/`**.

The CLI should expose targeted views:

```bash
pm next
pm show T-143
pm context T-143
pm blockers
pm changed
pm decisions T-143
pm handoff T-143
```

So the agent receives perhaps **2–8 KB of highly relevant context**, not 200 KB of project documentation.

---

# What I would build first

Not the Kanban.

I'd build this tiny core:

```bash
pm init
pm add
pm show
pm next
pm start
pm block
pm finish
pm verify
pm status
pm context
```

And these invariants:

### 1. No task can become `DONE` without verification

### 2. `pm next` only returns tasks whose dependencies are satisfied

### 3. Every state transition is recorded

### 4. Git is automatically associated with work

For example:

```bash
pm start T-143
```

records the current branch/commit.

Then:

```bash
pm finish T-143
```

records:

```yaml
completed_at:
completed_commit:
verification:
```

### 5. Humans can edit everything manually

The system should never become a proprietary database that you can't recover from.

---

# One thing I would NOT do

Don't make the global Antigravity skill automatically force:

> "Every agent must run `pm next`."

Instead make the protocol discoverable:

```text
If .project/ exists:
    load project protocol
    determine agent role
    request/claim appropriate task
```

Different agents may need different workflows.

A reviewer shouldn't necessarily call `pm next`.

A planning agent might create 20 tasks.

A coding agent might claim exactly one.

A test agent might consume tasks in `review`.

That gives you a genuine **multi-agent project execution protocol**, rather than a fancy TODO manager.

---

## And this has a potentially much bigger implication

You were recently thinking about a **software development factory / agent-assisted development platform**.

This could actually be the missing middle layer:

```text
                HUMAN
                  │
                  ▼
           ┌─────────────┐
           │ PROJECT PM  │
           │   ENGINE    │
           └──────┬──────┘
                  │
        ┌─────────┼─────────┐
        ▼         ▼         ▼
     Planner    Coder     Reviewer
       Agent      Agent      Agent
        │         │         │
        └─────────┼─────────┘
                  ▼
                Git
                  │
                  ▼
                CI/CD
```

The **single source of truth isn't merely project management**.

It's the **contract between humans, agents, code, verification and Git**.

That's worth building.

And because it can start as ~one portable CLI + schema + `.project/` convention, **you don't need to turn it into a SaaS/product before you get value from it yourself.**

**I would proceed — but build the protocol/engine first, not the dashboard.**
