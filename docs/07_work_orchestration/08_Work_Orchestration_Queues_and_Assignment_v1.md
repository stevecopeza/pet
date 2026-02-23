STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Work Orchestration, Queues, and Assignment (v1)

## Current asset (audit)

- `wp_pet_work_items` represent queueable work.
- `source_type` supports `ticket` and `project_task`.
- `wp_pet_department_queues` supports pull/assign tracking.

This is valuable and should become the unified operational queue.

## Required alignment to Ticket backbone

### Source normalization
End state:
- WorkItems for human work are primarily `source_type='ticket'`.
- `project_task` sources are legacy/compat until fully migrated.

### Department/Team ownership
Ticket carries owning department/team.
WorkItem is projected from Ticket and references:
- department_id
- required_role_id
- SLA snapshot/due dates (when applicable)

### Assignment modes
Ticket stores:
- preferred assignee
- actual assignee
- assignment mode

WorkItem mirrors assignment for scheduling and queue views.

## Queue mechanics (department-owned)

- Department owns the queue.
- Tickets enter queue upon creation or when status becomes active.
- Team members may pull if allowed.
- Department head may allocate.

## Manager visibility

Managers view:
- queue inventory
- assignment distribution
- SLA risk (derived from ticket due dates and SLA clock)

## No business logic in UI

UI actions call commands; domain enforces:
- who may assign
- who may pick up
- what transitions are legal
