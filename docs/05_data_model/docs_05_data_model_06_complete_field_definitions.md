# Complete Field Definitions -- Commercial Layer

## Quotes

id (UUID, PK) opportunity_id (UUID, FK, required) quote_number (varchar
50, unique) title (varchar 255, required) description (text, required)
customer_id (UUID, FK, required) currency (char 3, required) valid_from
(date) valid_until (date) version_number (int) supersedes_quote_id
(UUID, nullable) status (enum) total_sell_value (decimal 14,2)
total_internal_cost (decimal 14,2) total_margin (decimal 14,2)
created_by (UUID) created_at (datetime) updated_at (datetime)

## Quote Components

id (UUID) quote_id (UUID) component_type (enum: catalog, implementation,
recurring, adjustment) sort_order (int) sell_value (decimal 14,2)
internal_cost (decimal 14,2)

## Implementation Tasks

id (UUID) milestone_id (UUID) title (varchar 255) duration_hours
(decimal 8,2) role_catalog_item_id (UUID) department_snapshot (varchar
255) base_rate_snapshot (decimal 12,2) sell_rate_snapshot (decimal 12,2)
internal_cost_snapshot (decimal 14,2) sell_value_snapshot (decimal 14,2)
