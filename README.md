# TWC Variation Bought Together

**Version:** 1.0.0  
**Publisher:** That Web Company  
**Status:** Initial source release; staging integration testing required

A companion WordPress/WooCommerce plugin for [Bought Together for WooCommerce](https://woocommerce.com/products/bought-together-for-woocommerce/) (WP1). It adds product/variation recommendations for each variation of a variable product, rather than only allowing recommendations on the parent product.

## Requirements

- WordPress and WooCommerce.
- WP1 **Bought Together for WooCommerce** activated (developed against its supplied v1.5.7 source).
- PHP 7.4 or newer.
- Optional: **TWC Variation Subscription Link**. The admin field is moved beneath its **Linked Club Products** field when that label can be located.

This is **not** a standalone replacement for WP1's licensed plugin. Do not install both plugins' ZIP files as if they were alternatives.

## Install

1. Back up the website and use a staging environment.
2. Install and activate WooCommerce and the licensed WP1 Bought Together plugin.
3. Upload the `twc-variation-bought-together` plugin directory or a ZIP containing that directory from **Plugins → Add New Plugin → Upload Plugin**.
4. Activate **TWC Variation Bought Together**.
5. Edit a variable WooCommerce product and open a variation in **Product data → Variations**.
6. Select items in **Bought Together Products** (below **Linked Club Products** where possible), then save the product.
7. Test the product page, variation switching, item selection and final cart contents.

## Implementation

- Stores each variation's selected product IDs in the variation meta key `_twc_variation_bought_together_ids`.
- Supplies the selected variation's list to the WP1 plugin when it refreshes its offer via `bought_together_get_table_data` AJAX.
- Uses WP1's existing pricing, layout and add-to-cart handling; it does not alter Club subscription renewal behaviour.
- Seeds WP1's initial render using the first configured variation and hides the table until a variation is selected.
- Supports recommendations referring to simple products and specific purchasable product variations.

## Limitations and testing

- Integration depends on WP1's implementation and may require updates after WP1 version changes.
- Positioning depends on the actual markup of **TWC Variation Subscription Link**; its source was not available when v1.0.0 was built.
- This version does not offer a separate Elementor shortcode. Use the existing WP1 display hook/widget configuration until an integration is tested.
- Variation switching, initial visibility, AJAX updates, bundle totals and cart handling have **not been verified on the live site**.
- Club subscriptions, quantity rules, shipping and renewal flows must be tested separately.

## Source layout

```text
twc-variation-bought-together/
  twc-variation-bought-together.php
  assets/
    admin.js
    front.js
  README.txt
```

See [CHANGELOG.md](CHANGELOG.md) for version history.
