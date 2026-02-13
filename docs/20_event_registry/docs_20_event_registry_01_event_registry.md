# PET – Event Registry

## Purpose
Authoritative list of all valid PET domain events.

## Rules
- Event names are canonical
- Payloads are immutable
- No undocumented events allowed

## Events

### quote.accepted
- Aggregate: Quote
- Trigger: Quote accepted
- Payload: quote_id, accepted_by, accepted_at

### time.submitted
- Aggregate: TimeEntry
- Trigger: Time submission
- Payload: time_entry_id, employee_id, minutes

### ticket.warning
- Aggregate: Ticket
- Trigger: SLA warning threshold reached
- Payload: ticket_id

### ticket.breached
- Aggregate: Ticket
- Trigger: SLA breach threshold reached
- Payload: ticket_id

### ticket.escalation_triggered
- Aggregate: Ticket
- Trigger: Escalation rule condition met
- Payload: ticket_id, level

### delivery.milestone_completed
- Aggregate: Project
- Trigger: Milestone completion criteria met
- Payload: project_id, milestone_id

### commercial.change_order_approved
- Aggregate: ChangeOrder
- Trigger: Change Order approved
- Payload: change_order_id, approved_by

### delivery.project_created
- Aggregate: Project
- Trigger: Quote acceptance (converted to project)
- Payload: project_id, source_quote_id

**Authority**: Normative

