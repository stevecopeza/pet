## Governance (Approvals) & People (Leave/Capacity)

### Approvals Engine (Append-Only Decisions)
- pet_approval_requests: uuid, request_type, subject_type/id, status (pending|approved|rejected|cancelled), requested_by/at, decided_by/at, decision_reason, request_payload_json immutable.
- pet_approval_steps (optional): multi-step routing with approver_type/reference, status, decided_at, decision_reason.
- Rules: no hard deletes; decisions emit domain events; edits occur only via status transitions.

### Leave & Capacity Realism
- pet_leave_types: name, paid_flag.
- pet_leave_requests: uuid, employee_id, leave_type_id, dates, status (draft|submitted|approved|rejected|cancelled), submitted/approved timestamps, notes.
- pet_capacity_overrides: employee_id, effective_date, capacity_pct, reason; append-only overrides.
- Derived capacity = calendar windows + holidays + approved leave + latest override.

### Commands & UI
- RequestApproval, Approve/Reject/CancelRequest; Leave submit/approve/reject/cancel; SetCapacityOverride.
- UI: approvals queue/detail/history; leave requests (my/team), calendar overlay.
- Decisions create domain events; no direct table edits via UI.

### Tests
- State machine guards (illegal transitions blocked), append-only discipline, audit visibility of payload snapshots.
