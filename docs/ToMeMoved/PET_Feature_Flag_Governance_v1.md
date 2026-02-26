# PET SLA & Work Orchestration Feature Flag Governance v1.0

Date: 2026-02-26

## Purpose

Define controlled activation mechanisms for:

-   SLA Scheduler
-   WorkItem Projection Listener
-   Queue Visibility
-   Priority Engine

This enables behavioural rollback without schema rollback.

------------------------------------------------------------------------

## 1. Required Feature Flags

  ------------------------------------------------------------------------------
  Flag Key                       Default                Controls
  ------------------------------ ---------------------- ------------------------
  pet_sla_scheduler_enabled      false                  Cron-based SLA
                                                        evaluation

  pet_work_projection_enabled    false                  Ticket → WorkItem
                                                        listener

  pet_queue_visibility_enabled   false                  Queue endpoints & UI

  pet_priority_engine_enabled    false                  PriorityScoringService
                                                        activation
  ------------------------------------------------------------------------------

Flags MUST:

-   Default to false on upgrade
-   Be stored in config table (not transient)
-   Be environment overridable

------------------------------------------------------------------------

## 2. Activation Order

1.  Enable projection
2.  Enable SLA scheduler
3.  Enable priority engine
4.  Enable queue visibility

Order must not change.

------------------------------------------------------------------------

## 3. Behavioural Rollback

If issue detected:

-   Disable queue visibility first
-   Disable priority engine
-   Disable scheduler
-   Disable projection last

Schema remains intact.

------------------------------------------------------------------------

## 4. Operational Monitoring

On activation monitor:

-   Duplicate SLA events
-   Duplicate WorkItems
-   Long-running cron jobs
-   DB lock contention

------------------------------------------------------------------------

## Acceptance Criteria

-   Flags respected at runtime
-   No feature activates implicitly
-   Safe toggle on/off without fatal errors
