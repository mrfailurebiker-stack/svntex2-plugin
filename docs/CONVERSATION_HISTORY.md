# Conversation History (Abbreviated Summary)

This document captures the high-level evolution of the development session so work can seamlessly continue after editor restart.

## Phases
1. Stabilization: Fixed custom login/registration critical errors; added robust rewrite detection fallback; implemented DOB + 18+ validation and email validation.
2. Product & Inventory Base: Added `svntex_product` CPT, taxonomies (category, collection, tag), variant & inventory tables, delivery rules, media links table.
3. Storefront & Cart: Implemented product archive + single product variant selector UI, custom cart & checkout pages + REST endpoints (independent of Woo front-end).
4. Security: Added rolling hour-based public nonce for semi-public cart/checkout endpoints; restricted guest order lookups; cleaned header timing for cookies.
5. Admin Product Fields: Amazon-style fields (SKU, MRP, Sale Price, GST %, Cost Price (admin only), Profit Margin auto-calc, bullet points, gallery images, video URLs) with REST exposure (cost excluded from public output).
6. Error Remediation: Converted meta box rendering to HEREDOC to eliminate parse errors; removed trailing PHP close tags; fixed headers already sent warnings.
7. Vendor Layer: Introduced `svntex_vendors` table + CRUD REST endpoints + product vendor meta box + formatter extension.
8. Inventory Enforcement: Added transactional batch stock decrement with locking, automatic product `stock_status` sync, low stock and out-of-stock action hooks.
9. Notifications & Settings (Latest): Implemented low/out-of-stock email notifications, settings UI for threshold + recipients, REST stock settings endpoint.

## Key Hooks
- `svntex2_low_stock_variant( $variant_id, $remaining, $threshold )`
- `svntex2_out_of_stock_variant( $variant_id )`
- `svntex2_product_formatter_extra` (filter to extend product API response)

## REST Endpoints Added (Recent)
- `GET/POST /svntex2/v1/stock/settings`
- `GET /svntex2/v1/variant/{id}/inventory`
- Vendor CRUD: `/svntex2/v1/vendors`, `/svntex2/v1/vendors/{id}`

## Options Added
- `svntex2_low_stock_threshold`
- `svntex2_stock_notify_enabled`
- `svntex2_stock_notify_emails`

## Next Planned Tasks
1. Order status workflow (processing, shipped, delivered, cancelled) + audit log.
2. Admin order list & detail UI with transitions.
3. Vendor order grouping/export + optional automated notifications.
4. Reporting enhancements (inventory valuation, sales metrics).
5. Customer management expansions & capability refinements.

## Integrity Notes
- Profit margin hidden for non-admin users in product formatter.
- Email alerts throttled (6h low-stock, 3h out-of-stock) per variant.
- Transactional decrements prevent oversell and keep product stock_state aligned.

(Generated for continuity; safe to extend.)
