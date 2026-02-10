# PET – Domain Model: State Machines

## Purpose of this Document
This document defines the **authoritative state machines** for PET domain entities.

It specifies:
- Allowed states
- Permitted transitions
- Forbidden transitions
- Mandatory resolution points

All state transitions are enforced via **hard blocks** by default.

---

## Global State Machine Rules

- State transitions are explicit, never implicit
- Illegal transitions are **blocked immediately**
- Errors must be resolved before continuation
- No background or deferred correction is permitted

State machines are enforced in the **domain layer**.

---

## Lead State Machine

States:
- Created
- Qualified
- Disqualified
- Archived

Allowed transitions:
- Created → Qualified
- Created → Disqualified
- Qualified → Archived
- Disqualified → Archived

Forbidden transitions:
- Disqualified → Qualified
- Archived → Any

---

## Qualification State Machine

States:
- Started
- Completed
- Archived

Allowed transitions:
- Started → Completed
- Completed → Archived

Forbidden transitions:
- Archived → Any

---

## Opportunity State Machine

States:
- Created
- Active
- Quoted
- Won
- Lost
- Archived

Allowed transitions:
- Created → Active
- Active → Quoted
- Quoted → Won
- Quoted → Lost
- Won → Archived
- Lost → Archived

Forbidden transitions:
- Lost → Won
- Archived → Any

---

## Quote State Machine

States:
- Draft
- Sent
- Accepted
- Locked
- Rejected
- Archived

Allowed transitions:
- Draft → Sent
- Sent → Accepted
- Accepted → Locked
- Sent → Rejected
- Locked → Archived
- Rejected → Archived

Forbidden transitions:
- Locked → Any
- Rejected → Any except Archived

Notes:
- Any modification attempt on Locked quotes is blocked
- Changes require new Quote entities

---

## Sale State Machine

States:
- Created
- Fulfilled
- Archived

Allowed transitions:
- Created → Fulfilled
- Fulfilled → Archived

Forbidden transitions:
- Fulfilled → Created

---

## Project State Machine

States:
- Created
- Planned
- Active
- Completed
- Cancelled
- Archived

Allowed transitions:
- Created → Planned
- Planned → Active
- Active → Completed
- Active → Cancelled
- Completed → Archived
- Cancelled → Archived

Forbidden transitions:
- Completed → Active
- Cancelled → Active

---

## Milestone State Machine

States:
- Defined
- Active
- Completed
- Archived

Allowed transitions:
- Defined → Active
- Active → Completed
- Completed → Archived

Forbidden transitions:
- Completed → Active

---

## Task State Machine

States:
- Planned
- In Progress
- Completed
- Archived

Allowed transitions:
- Planned → In Progress
- In Progress → Completed
- Completed → Archived

Forbidden transitions:
- Completed → In Progress

Additional rules:
- Time logging is forbidden on Completed or Archived tasks

---

## Time Entry State Machine

States:
- Draft
- Submitted
- Locked

Allowed transitions:
- Draft → Submitted
- Submitted → Locked

Forbidden transitions:
- Submitted → Draft
- Locked → Any

---

## Ticket State Machine

States:
- Created
- Active
- Resolved
- Closed
- Archived

Allowed transitions:
- Created → Active
- Active → Resolved
- Resolved → Closed
- Closed → Archived

Forbidden transitions:
- Closed → Active

---

## SLA State Machine

States:
- Defined
- Active
- Expired
- Archived

Allowed transitions:
- Defined → Active
- Active → Expired
- Expired → Archived

Forbidden transitions:
- Expired → Active

---

## Knowledge Article State Machine

States:
- Draft
- Published
- Revised
- Archived

Allowed transitions:
- Draft → Published
- Published → Revised
- Revised → Published
- Published → Archived

Forbidden transitions:
- Archived → Any

---

## Error Handling Policy

When an illegal transition is attempted:

- The action is blocked
- The error is explicit
- The user must resolve the issue immediately

No silent fallback is permitted.

---

**Authority**: Normative

This document defines all allowed state transitions in PET. Implementation must conform.

