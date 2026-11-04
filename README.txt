TWC Variation Bought Together v1.0.5
===================================

Companion extension for WooCommerce and the licensed WP1
"Bought Together for WooCommerce" plugin (tested against source v1.5.7).

INSTALL
1. Install on staging and back up first.
2. Keep WooCommerce and WP1 Bought Together active.
3. Upload the repository ZIP using WordPress > Plugins > Add New.
4. Edit a variable product > Variations. Set Bought Together products in
   each variation after the Linked Club Products selector.
5. Remove the temporary [bought_together_table] shortcode widget if you
   added one during debugging. Use the regular Elementor WooCommerce
   Add to Cart widget; the companion renders its section directly after
   that product's Add to Cart form.
6. You may remove the old parent-level Bought Together recommendation;
   it is not required for variation-managed products as of v1.0.4.
7. Clear site caches and verify each variation before enabling in production.

HOW IT WORKS
- Variation IDs are persisted in _twc_variation_bought_together_ids.
- Parent metadata is never intercepted or altered.
- On managed variable products, the WP1 auto-render callback is disabled
  while the WP1 stylesheet and Add Both to Cart AJAX handler remain active.
- An empty wrapper is emitted within the standard WooCommerce product
  Add to Cart form lifecycle. Selecting a variation calls the companion's
  separate nonce-verified AJAX endpoint.
- The response includes the selected variation plus only its mapped
  purchasable, in-stock simple products or specific variations.
- Empty or invalid selections hide the entire section.
- WP1 handles button clicks and cart additions using the familiar
  data-prd-id, data-attribute and nonce conventions.

LIMITATIONS / QA
- Exact Elementor template, variation switching, taxes, price formatting,
  product quantities and real cart calculations require staging QA.
- Variation quantity defaults to one for each selected item, matching WP1.
- Recommendation types are limited to purchasable simple products or
  specific purchasable variations (not subscription parents).
- This version deliberately avoids modifying Club subscription renewal
  behaviour. Confirm subscription mapping, shipping and renewal separately.
- Removing all mapped products may leave the original WP1 display intact
  on products that never had a saved variation configuration.
- If an Elementor template does not run woocommerce_after_add_to_cart_form,
  the automatic companion section will not be placed in that template.
- Row layout is implemented directly. Other WP1 layouts are not
  promised as identical.
- WP1 plugin upgrades can change the Add Both to Cart protocol.
- Only static code checks were performed; no live frontend checkout
  validation was performed for this release.

VERSION HISTORY
1.0.5: After a successful WP1 Bought Together AJAX add, trigger the
       FunnelKit Side Cart refresh/open event (fkcart_update_side_cart).
       Failed requests and redirect responses do not open the drawer.
       The original WP1 button/cart handler is unchanged.
1.0.4: Use a dedicated variation renderer and AJAX endpoint, preserve WP1
       Add Both to Cart event processing, remove parent bootstrap.
1.0.3: Preserve parent rendering during initial page load.
1.0.2: Detect Row and Table checkbox layouts.
1.0.1: Hide unmapped variation offers.
1.0.0: Initial variation selector.
