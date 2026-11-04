/* global jQuery */
(function ($) {
  'use strict';
  $(function () {
    var $box = $('#bought-together-products-data');
    if (!$box.length) return;
    var $container = $box.parent();
    // The WP1 markup contains its own outer wrapper/clearfix; hide the inner table
    // until a valid WooCommerce variation is selected so the seed list isn't misleading.
    $box.hide();
    function selected() {
      return parseInt($('form.variations_form input.variation_id').first().val(), 10) || 0;
    }
    function syncDisplay() {
      var hasRecommendations = $box.find('input[type="checkbox"]').length > 1;
      $box.toggle(selected() > 0 && hasRecommendations);
    }
    $('form.variations_form').on('found_variation show_variation', function () {
      // The original plugin issues its own AJAX refresh on show_variation.
      $box.hide();
    }).on('reset_data hide_variation', function () { $box.hide(); });
    $(document).ajaxComplete(function (_event, _xhr, options) {
      if (options && typeof options.data === 'string' && options.data.indexOf('action=bought_together_get_table_data') !== -1) {
        // The original plugin's success callback updates the markup before ajaxComplete.
        syncDisplay();
      }
    });
    // Default variation may already have been resolved before this file executes.
    if (selected()) syncDisplay();
  });
})(jQuery);
