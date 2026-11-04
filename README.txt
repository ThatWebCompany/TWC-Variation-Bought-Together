TWC Variation Bought Together v1.0.0
===================================

This is a companion plugin, not a standalone replacement. Keep the licensed
"Bought Together for WooCommerce" plugin (WP1) active alongside WooCommerce.

INSTALLATION
1. Backup the site and use staging first.
2. Plugins > Add New Plugin > Upload Plugin > choose the ZIP and activate.
3. Edit a variable product > Variations > open one variation.
4. Look for "Bought Together Products" below "Linked Club Products".
   The field is rendered at the bottom of variation settings and moved after
   the linked Club field by its visible label. If the Club plugin uses a
   different label or unusual markup, the field remains at the bottom.
5. Search for products/variations and select the recommendations; save product.
6. Test storefront variation switching and "Add all to cart" for each variation.

BEHAVIOUR
- Variation-specific selections are stored in variation post meta.
- Uses the WP1 plugin's own front-end rendering, options and cart AJAX.
- Existing parent product Bought Together settings are not modified.
- If at least one variation has recommendations, the addon seeds initial WP1
  output, then hides it until the chosen variation is refreshed via AJAX.
- An empty mapping for a selected variation produces no offer.
- Supports normal simple and concrete variation recommendations only.
- Existing WooCommerce subscription products/renewal flows are NOT altered.

IMPORTANT
- Built against supplied WP1 Bought Together for WooCommerce 1.5.7 source.
- Exact integration order with TWC Variation Subscription Link has not been
  verified because that plugin's code was not supplied.
- The original plugin's add-to-cart AJAX does not automatically add a variation
  subscription; existing Club products should be tested separately.
- No live WordPress staging/production tests have been performed.
- WP1 plugin upgrades may change the expected AJAX action or metadata key.
