# DEV NOTES (Implementation Only)

## Run Test Suite
- PHP unit + integration:
  - `cd wp-content/plugins/pet`
  - `composer test -n`
- E2E (optional UI smoke):
  - `npx playwright test`

## Mock QuickBooks Push/Pull
- Push (Outbox dispatcher simulates QuickBooks send):
  - Create a billing export, add items, queue it via REST or handlers
  - Run dispatcher in application code (e.g., trigger from admin or via container):
    - `OutboxDispatcherService->dispatchQuickBooks()`
  - Verifies:
    - `pet_outbox` rows marked `sent`
    - `pet_external_mappings` records `quickbooks` invoice mapping
    - `pet_qb_invoices` upserted with deterministic `qb_invoice_id` and `doc_number`
- Pull (Mock service):
  - `QbMockPullService->pullInvoices(customerId)`
  - `QbMockPullService->pullPayments(customerId)`
  - Verifies idempotent upserts in `pet_qb_invoices` and `pet_qb_payments`

## UI Verification
- Admin → Finance:
  - Billing Exports: list/detail, add items, queue
  - QuickBooks Invoices: read-only table
  - QuickBooks Payments: read-only table

## Notes
- Event stream is insert-only; monotonic aggregate version enforced
- Transactionality: command handlers write events and outbox in a single DB transaction
- No real QuickBooks calls; deterministic IDs and payloads only
