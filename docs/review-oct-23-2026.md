## Validation findings — hold Phase 2 activation

I reviewed PR #73, its CI evidence, Issues #8–#18, and the TravelOS integration backlog. Do not begin Issue #10 implementation yet.

### 1. Revisit Issue #9 / PR #73

PR #73 is merged and the reported CI checks are green, but source review identified a maker-checker authorization gap:

- The API accepts a client-supplied `approver_id`; the reviewed code does not establish that the named person actually authorized the transaction.
- `maker_checker_require_distinct_users` defaults to true in configuration, but the validation logic defaults `require_distinct_approver` to false when it is not supplied.
- The API accepts the distinct-approver requirement as request input, so the caller can influence the policy.
- Existing tests cover self-approval rejection but do not prove that a forged, different approver ID is rejected.

Reopen Issue #9 or create a clearly linked blocking follow-up. Implement trusted server-side approval authorization, enforce the configured policy independently of client input, and add regression tests for forged approver IDs and policy-bypass attempts. Do not describe maker-checker authorization as complete until those tests pass and the fix is reviewed.

### 2. Correct Issue #8 status

Issue #8 is closed despite its own acceptance criteria requiring completion of every child task. Issues #40–#43 remain open.

Reopen Issue #8 and keep it open until atomic posting/audit, failure recovery, company-scoped reference uniqueness and production-engine certification have all passed their acceptance criteria with evidence.

### 3. Reconcile the canonical DAG

Update Issue #18 to reflect the actual current main SHA and PR/issue states. Remove or replace stale references to the earlier main SHA and PR #70. Distinguish merged code, passing CI, accepted task completion and release readiness.

### 4. Execution order

- Keep T-068 / Issue #10 registered, but queued rather than active.
- Prioritize the Issue #9 authorization gap and the remaining #8 posting-integrity sequence, with #40 as the next core task once the blocking correction is resolved.
- Do not close #8 or mark Phase 1 complete while #40–#43 remain incomplete.
- Keep TravelOS tasks #60–#67 gated by the actual accounting contracts and prerequisites stated in their issues. Do not interpret closure of audit issue #59 as proof of a working integration.

### 5. Evidence required

For each correction, provide the merged PR, actual commit SHA, required green CI links, regression-test results, and a short acceptance-criteria checklist. Clearly identify any behavior not tested or not verified.

Do not deploy or grant pilot access based solely on green CI or closed issues.

## Review after PR 74 merged:

6. Two remaining actions before proceeding
A. Correct Issue #18 again. Its latest recorded status still says PR #74 is in review and identifies b14c51e... as the current main SHA. Both became stale when PR #74 merged. Update the canonical DAG to record merge commit ed91c14... and Issue #9's new state.

B. Resolve the approval-workflow design before production. The new checks materially improve authorization, but the current approach still accepts a reusable approver password or token in the posting request. Also, the controller sets the authenticated user as the maker, which means the authenticated-supervisor-session route cannot straightforwardly approve a transaction created by a different maker in that same request flow.

This does not negate the security improvement, but it warrants a dedicated approval-flow review: ideally, a separately authenticated approval action bound to the exact pending voucher, amount, company and payload, with replay protection and auditable approval evidence. Avoid treating password/token submission as a fully designed maker-checker workflow.
Decision
Keep T-068 on hold. PR #74 is merged, and the specific forged-approver findings have regression coverage. Before moving to account mapping, update Issue #18, inspect the final merged approval behavior, and have a maintainer accept the remaining design risks.

Issue #8 must remain open until its atomicity, failure recovery, reference uniqueness and production-engine certification evidence is formally reconciled. Once those gates and the approval review are resolved, Issue #10 can proceed without losing the coordinated execution order.