# Changelog

## 1.0.1 — Empty-variation visibility fix (2026-11-04)

- An unassigned variation now hides the Bought Together recommendations rather than falling back to the parent product.
- A variable product with no variation recommendations no longer renders a stale parent-level offer.
- Front-end code tracks selected variation IDs and only reveals refreshed offers with one or more recommended products.
- Preserves original WP1 bundle layout, totals and add-to-cart processing.

**Validation:** Source change committed; staged storefront and Elementor QA still required.


## 1.0.0 — Initial release (2026-11-04)

- Added a WooCommerce product search selector per variation.
- Persisted distinct Bought Together product mappings for each variation.
- Integrated with WP1 Bought Together for WooCommerce's variation AJAX refresh using WordPress metadata filtering.
- Included an admin layout helper to move the selector below **Linked Club Products** when available.
- Included front-end visibility handling for initial seed recommendations and variation changes.
- Documented dependency and untested staging/Elementor/Club subscription integration scenarios.

**Validation:** PHP syntax checked and ZIP contents inspected. Live WordPress functionality and visual QA not yet verified.
