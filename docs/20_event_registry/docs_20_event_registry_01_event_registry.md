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

**Authority**: Normative

