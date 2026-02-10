# PET – Domain Model: Relationships and Lifecycles

## Purpose of this Document
This document defines **how PET domain entities relate to one another** and **how they move through their lifecycles**.

It establishes:
- Mandatory vs optional relationships
- Creation and termination rules
- Visibility and immutability guarantees

No workflows or UI behaviour are defined here — only structural truth.

---

## Global Lifecycle Rules

### Visibility
- All entities remain **visible forever** unless explicitly archived
- Archival removes entities from default operational views, not history

### Immutability at Terminal States
- Once an entity reaches a terminal state, it becomes **immutable**
- Re‑opening is not permitted; new entities must be created instead

This preserves analytical and legal integrity.

---

## Identity Relationships

### Customer

- A Customer may have **many Sites**
- A Customer may have **many Contacts**
- A Customer anchors:
  - Opportunities
  - Quotes
  - Sales
  - Projects
  - Tickets

A Customer cannot be deleted if any dependent entities exist.

---

### Site

- A Site belongs to **exactly one Customer**
- A Site may have **many Contacts**
- A Site may anchor Projects and Tickets

Sites inherit commercial context from their Customer.

---

### Contact

- A Contact may belong to **multiple Customers**
- A Contact may belong to **multiple Sites**
- A Contact may be associated with:
  - Leads
  - Opportunities
  - Tickets

Contacts retain identity even if relationships change.

---

## Commercial Lifecycle

### Lead

Lifecycle:

```
Created → Qualified → Converted → Archived
              ↓
            Disqualified → Archived
```

Rules:
- Leads may exist without Customers
- Leads are mutable until qualification

---

### Qualification

Lifecycle:

```
Started → Completed → Archived
```

Rules:
- Qualification consumes measurable effort
- Completion gates Opportunity creation

---

### Opportunity

Lifecycle:

```
Created → Active → Quoted → Won → Archived
                     ↓
                   Lost → Archived
```

Rules:
- Opportunities require a Customer
- Gold / Silver / Bronze classification affects resource allocation

---

### Quote

Lifecycle:

```
Draft → Sent → Accepted → Locked → Archived
             ↓
           Rejected → Archived
```

Rules:
- Draft and Sent states are mutable
- Accepted quotes become Locked and immutable
- Changes require delta or cloned quotes

---

### Sale

Lifecycle:

```
Created → Fulfilled → Archived
```

Rules:
- Sale creation is triggered by quote acceptance
- Sale is immutable once created

---

## Delivery Lifecycle

### Project

Lifecycle:

```
Created → Planned → Active → Completed → Archived
                         ↓
                       Cancelled → Archived
```

Rules:
- Projects inherit constraints from Sales where applicable
- Completed projects are immutable

---

### Milestone

Lifecycle:

```
Defined → Active → Completed → Archived
```

Rules:
- Milestones aggregate Tasks
- Completion locks cumulative values

---

### Task

Lifecycle:

```
Planned → In Progress → Completed → Archived
```

Rules:
- Tasks must belong to a Project or operational bucket
- Completed tasks are immutable

---

## Time and Resource Lifecycle

### Time Entry

Lifecycle:

```
Draft → Submitted → Locked
```

Rules:
- Draft time may be edited by the owner
- Submitted time is immutable

---

## Support Lifecycle

### Ticket

Lifecycle:

```
Created → Active → Resolved → Closed → Archived
```

Rules:
- All support work must map to a Ticket
- Closed tickets are immutable

---

### SLA

Lifecycle:

```
Defined → Active → Expired → Archived
```

Rules:
- SLA breaches are recorded as events
- SLAs do not retroactively apply

---

## Knowledge Lifecycle

### Knowledge Article

Lifecycle:

```
Draft → Published → Revised → Archived
```

Rules:
- Revisions create new versions
- Historical versions remain accessible

---

## What This Enables

- Deterministic KPIs
- Safe reporting across years
- Legal and contractual defensibility

---

**Authority**: Normative

This document defines how PET entities relate and evolve. Violations are not permitted.

