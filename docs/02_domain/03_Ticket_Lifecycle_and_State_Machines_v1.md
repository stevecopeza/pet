STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Ticket Lifecycle and State Machines (v1)

## Principle

Ticket lifecycle must be **context-governed** by `lifecycle_owner` / `primary_container`, but stored on the single Ticket entity.

## Status domains

Define a single status field with context-specific allowed transitions.

### Support lifecycle (lifecycle_owner='support')
Typical statuses (example; final list must match existing support values while extending safely):
- `new` → `open` → `pending` → `resolved` → `closed`
Rules:
- `opened_at` set when leaving `new`
- `responded_at` set on first agent response (as implemented by SLA)
- `closed_at` set when closed

### Project lifecycle (lifecycle_owner='project')
Suggested statuses:
- `planned` → `ready` → `in_progress` → `blocked` → `done` → `closed`
Rules:
- baseline tickets may be `baseline_locked`
- roll-up tickets track derived progress only

### Internal lifecycle (lifecycle_owner='internal')
Suggested statuses:
- `planned` → `in_progress` → `done` → `closed`

## Leaf-only time logging rule

- If ticket has children (parent_ticket_id referenced by others), mark `is_rollup=1`.
- Domain must reject time logging to roll-up tickets.
- Roll-up progress/time/cost are computed (projection) from leaves.

## Assignment semantics (queue model)

Ticket stores:
- `owning_team_id` / `department_id`
- `preferred_assignee_id` (optional)
- `assigned_to_id` (optional)
- `assignment_mode` ENUM('PREFERRED_PERSON','TEAM_QUEUE_PULL','MANAGER_ALLOCATED')

Rules:
- owning team/department is always set (or derivable) for support/project/internal tickets.
- assignment events update WorkItem projection.

## Domain events (required points)

Ticket should emit events for:
- TicketCreated
- TicketUpdated (optional granular events: status changed, priority changed)
- TicketAssigned
- TicketSplit (parent becomes roll-up, children created)
- TicketLinkAdded (optional)
- TicketCommercialContextChanged (rare; should be blocked post-acceptance baseline)

Events must remain additive and backward compatible.

## Guardrails

- Cross-context updates must not violate lifecycle ownership.
- Baseline lock: once a ticket is marked as quote-baseline accepted, sold fields become immutable.
- Changes after acceptance are represented as new tickets (change order / variance).
