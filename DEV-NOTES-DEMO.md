# DEV NOTES — Demo Seed & Purge

## Seed demo_full
- REST:
  - POST /wp-json/pet/v1/system/demo/seed_full
  - Returns: seed_run_id and summary
- Generates seed_run_id (UUID), writes metadata where supported
- Only adjusts timestamps to keep dataset recent (ordering preserved)

## Purge by seed_run_id
- REST:
  - POST /wp-json/pet/v1/system/demo/purge
  - Body: { seed_run_id: "<UUID>" }
- Deletes untouched seeded rows; archives touched where archived_at exists
- Preserves immutable:
  - quotes with accepted_at
  - time entries with submitted/approved status or submitted_at
  - billing exports queued/sent/confirmed
  - domain event stream

## Validation tests
- PHP:
  - cd wp-content/plugins/pet
  - composer test -n
- Includes:
  - DemoSeedValidationTest: basic completeness and purge preservation

## Known limitations
- Seeding covers key identity/customers/leave/billing shadow data; other sections currently stubbed and will be expanded
- Touched tracking service exists; integrate into handlers incrementally
