# PET – Domain Model: Entities Overview

## Purpose of this Document
This document defines the **authoritative set of domain entities** in PET and their high‑level responsibilities.

It does not define workflows or state transitions (those are covered later). It answers one question only:

> **What exists in the PET domain, and why?**

All entities listed here are part of a **single unified domain model**, organised into bounded contexts for clarity — not isolation.

---

## Structural Rules

- Every entity has a stable identity
- Every entity has a lifecycle
- Every entity emits events
- No entity exists without purpose

Entities are not UI constructs and are not equivalent to WordPress concepts.

---

## Core Identity Context

### Customer
Represents a business PET has or may have a commercial relationship with.

Responsibilities:
- Acts as the primary commercial counterparty
- Anchors sales, delivery, support, and finance context

Notes:
- Never hard‑deleted
- May be archived

---

### Site
Represents a physical or logical location belonging to a Customer.

Responsibilities:
- Provides location‑specific context
- Supports multi‑site customers

Notes:
- Always belongs to exactly one Customer

---

### Contact
Represents a person external to the organisation.

Responsibilities:
- Acts as a communication endpoint
- May be linked to multiple Customers and Sites

Notes:
- Identity is stable even if affiliations change

---

## Organisation Context

### Employee
Represents a person performing work within PET.

Responsibilities:
- Generates time entries
- Owns actions and events
- Is subject to KPIs

Notes:
- Separate from WordPress user lifecycle

---

### Team
Represents a logical grouping of Employees.

Responsibilities:
- Visibility and aggregation
- Managerial oversight

Notes:
- Employees may belong to multiple teams

---

## Commercial Context

### Lead
Represents unqualified potential business.

Responsibilities:
- Capture inbound or discovered opportunity
- Hold incomplete or messy data

---

### Qualification
Represents structured assessment of a Lead.

Responsibilities:
- Establish minimum understanding
- Gate progression to Opportunity

---

### Opportunity
Represents qualified commercial intent.

Responsibilities:
- Track pre‑sales investment
- Drive quote creation

---

### Quote
Represents a formal commercial offer.

Responsibilities:
- Define scope, cost, and time expectations
- Act as a binding artifact once signed

Notes:
- Versioned
- Immutable once accepted

---

### Sale
Represents acceptance of a Quote.

Responsibilities:
- Transition intent into obligation
- Trigger delivery setup

---

## Delivery Context

### Project
Represents structured delivery work.

Responsibilities:
- Enforce sold constraints
- Track progress vs plan

---

### Milestone
Represents a commercially meaningful grouping of work.

Responsibilities:
- Aggregate tasks
- Provide progress checkpoints

---

### Task
Represents a unit of planned work.

Responsibilities:
- Anchor time tracking
- Enable execution detail

---

## Time and Resource Context

### Time Entry
Represents recorded time spent by an Employee.

Responsibilities:
- Provide factual record of effort
- Feed KPIs and billing

Notes:
- Append‑only

---

## Support Context

### Ticket
Represents a request for assistance.

Responsibilities:
- Capture support demand
- Enforce SLA where applicable

---

### SLA
Represents a service commitment.

Responsibilities:
- Define response and resolution expectations
- Generate breach events

---

## Knowledge Context

### Knowledge Article
Represents curated operational knowledge.

Responsibilities:
- Capture solutions and learnings
- Reduce future effort

---

## Measurement Context

### Event
Represents an immutable fact.

Responsibilities:
- Feed KPIs
- Populate activity feed

---

### KPI
Represents a derived indicator.

Responsibilities:
- Describe performance
- Support decision‑making

---

## Cross‑Cutting Context

### Activity Feed Item
Represents a rendered view of events.

Responsibilities:
- Provide situational awareness
- Preserve auditability

---

## What This Document Deliberately Avoids

- Workflow definitions
- State transitions
- UI behaviour
- Database schemas

Those are defined in subsequent documents.

---

**Authority**: Normative

This document defines what exists in PET. Entities not listed here do not exist.

