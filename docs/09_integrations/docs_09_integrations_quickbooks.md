# PET – QuickBooks Integration possess

## Purpose
Defines the integration contract between PET and QuickBooks.

---

## Authority Boundary

- PET owns invoice intent and time truth
- QuickBooks owns ledger execution and payments

---

## Triggering Events

- InvoiceIntentCreated
- InvoiceIntentUpdated (delta only)

---

## Data Sent to QuickBooks

- Customer identifier
- Line items (mapped from time/products)
- Tax context

---

## Data Received from QuickBooks

- Invoice status
- Payment confirmation
- Rejection or adjustment notice

---

## Failure Handling

- Sync failures create reconciliation tasks
- No overwrite of PET data

---

**Authority**: Normative

