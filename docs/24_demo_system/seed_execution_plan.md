# PET Demo Seed Execution Plan v1.1

Version: 1.1\
Date: 2026-02-14\
Status: Binding (Seed Pipeline + Determinism)

## Purpose

Define the deterministic, ordered demo seed pipeline and its failure
behavior.

## Core Rules

-   Seed uses **application commands/handlers** (preferred) or domain
    mutation APIs; no direct property setting.
-   Every created entity is recorded in the Demo Seed Ledger with
    `seed_run_id`.
-   Every transition must be preceded by readiness validation (see
    Readiness Gates).
-   On transition failure, seed must **degrade** (leave entity in
    closest legal earlier state) unless the artifact is demo-critical
    for PASS.

## Pipeline Overview (Steps)

1.  `seed.meta` --- create seed run id + seed ledger context
2.  `seed.customers` --- customer + site + contact anchors
3.  `seed.org` --- teams + employees + memberships (if available)
4.  `seed.quotes.draft` --- Q1..Q4 drafts with deterministic content
5.  `seed.quotes.autofill` --- ensure readiness for Q1/Q4 acceptance
6.  `seed.quotes.accept` --- accept Q1 and Q4 (demo accepted quotes)
7.  `seed.projects` --- create project P1 from accepted Q1
8.  `seed.milestones` --- create milestone M1 on P1 and complete it (if
    required)
9.  `seed.tickets` --- create ticket T1 under P1
10. `seed.sla` --- assign SLA policy, init clock, evaluate idempotently
11. `seed.time.draft` --- create 2--3 time entries
12. `seed.time.submit` --- submit W1 time entry (immutable)
13. `seed.advisory` --- ensure feed + projections present
14. `seed.summary` --- return counts + anchors + step results

## Failure Modes

### Domain failure during acceptance/submission/etc.

-   Record a step issue:
    -   `error=domain_exception`
    -   include message, entity key (Q1/Q4), and gate that failed
-   Degrade:
    -   keep quote Draft/Ready (do not accept)
    -   keep time Draft (do not submit)
-   If degraded artifact is required for PASS (see Success Criteria),
    mark `overall=FAIL` and return 422 **only if** the dataset cannot be
    considered demoable.

### Schema capability missing

-   If required tables/columns missing:
    -   Preflight should have failed; seed may proceed in PARTIAL mode
        if allowed.
    -   Step is marked `SKIPPED_CAPABILITY` and overall becomes PARTIAL.

## Deterministic Content Rules

-   All names must use `DEMO ...` prefixes.
-   Amounts/rates must be fixed constants.
-   IDs are system-generated but anchor mapping must be returned as
    stable keys.

## Mermaid: Pipeline

``` mermaid
flowchart TD
  A[seed.meta] --> B[customers]
  B --> C[org]
  C --> D[quotes draft]
  D --> E[quotes autofill]
  E --> F[quotes accept]
  F --> G[projects]
  G --> H[milestones]
  H --> I[tickets]
  I --> J[sla]
  J --> K[time draft]
  K --> L[time submit]
  L --> M[advisory]
  M --> N[summary]
```
