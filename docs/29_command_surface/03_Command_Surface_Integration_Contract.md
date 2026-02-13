# PET Command Surface -- Cross-Engine Integration v1.0

## Event Projection Model

All engines emit domain events.

Projection Service maps domain events → FeedEvent.

Example Mappings:

SLAWarning → classification=critical SLABreach → classification=critical
EscalationTriggered → classification=operational
ProjectMilestoneAchieved → classification=informational
ChangeOrderApproved → classification=operational

No module writes directly to FeedEvent table.

## Audience Resolution

Rules: - SLA events → assigned user + department head - Project
milestone → project team - Commercial wins → global - Advisory signals →
management roles

## Acknowledgement Escalation

If acknowledgement_required and deadline exceeded: - Emit
AnnouncementUnacknowledged event - Notify direct manager - Escalate
upward after configurable interval

## Retention Policy

-   Operational events retained 90 days
-   Strategic announcements retained indefinitely
-   SLA breach events archived after 180 days
