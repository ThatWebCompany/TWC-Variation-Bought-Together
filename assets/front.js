/* global jQuery, twcVariationBoughtTogether */
(function ($) {
  'use strict';
  $(function () {
    var $box = $('#bought-together-products-data');
    if (!$box.length) return;

    var configured = (typeof twcVariationBoughtTogether !== 'undefined' &&
      twcVariationBoughtTogether.variations) || {};
    var $forms = $('form.variations_form');
    var lastRequested = 0;

    function chosenVariation() {
      return parseInt($forms.first().find('input.variation_id').val(), 10) || 0;
    }
    function hasAssignedProducts(id) {
      return id > 0 && Object.prototype.hasOwnProperty.call(configured, String(id)) &&
        Number(configured[String(id)]) > 0;
    }
    function hasRenderedRecommendations() {
      // WP1 renders one checkbox for the selected main variation, then one per
      // recommendation. Never show the table for the main variation alone.
      return $box.find('.bought_together_tbl input[type="checkbox"]').length > 1;
    }
    function hideOffer() {
      $box.hide();
    }
    function syncOffer(requestedVariation) {
      var current = chosenVariation();
      if (!current || current !== requestedVariation ||
          !hasAssignedProducts(current) || !hasRenderedRecommendations()) {
        hideOffer();
        return;
      }
      $box.show();
    }

    // The WP1 initial HTML uses a seed recommendation from another variation.
    // Never display the seed until the actual selected variation has refreshed.
    hideOffer();

    $forms.on('found_variation show_variation', function (_event, variation) {
      lastRequested = parseInt(variation && variation.variation_id, 10) || 0;
      hideOffer();
    });
    $forms.on('reset_data hide_variation', function () {
      lastRequested = 0;
      hideOffer();
    });

    $(document).ajaxComplete(function (_event, _xhr, options) {
      if (!options || typeof options.data !== 'string' ||
          options.data.indexOf('action=bought_together_get_table_data') === -1) return;

      var matched = options.data.match(/(?:^|&)_varid=(\d+)/);
      var responseVariation = matched ? parseInt(matched[1], 10) : 0;
      syncOffer(responseVariation);
    });

    // A variation may be preselected on initial page load. Initiate its
    // refresh even if WooCommerce fired show_variation before this listener.
    var initial = chosenVariation();
    if (hasAssignedProducts(initial) &&
        typeof $.fn.refreshBoughtTogetherTableData === 'function') {
      lastRequested = initial;
      $box.refreshBoughtTogetherTableData(String(initial), false, initial);
    }
  });
})(jQuery);
