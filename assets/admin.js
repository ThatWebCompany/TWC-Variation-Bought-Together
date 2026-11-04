/* global jQuery */
(function ($) {
  'use strict';
  function positionFields(root) {
    $(root).find('.woocommerce_variation').each(function () {
      var variation = $(this), field = variation.find('.twc-variation-bt-field');
      if (!field.length) return;
      var anchor = variation.find('label').filter(function () {
        return /linked\s+club\s+products?/i.test($(this).text());
      }).first();
      if (anchor.length) {
        var wrapper = anchor.closest('.form-row, .form-field, p');
        if (wrapper.length && !wrapper.hasClass('twc-variation-bt-field')) {
          field.insertAfter(wrapper);
        }
      }
    });
  }
  $(function () {
    positionFields(document);
    $(document).on('woocommerce_variations_loaded woocommerce_variations_added', function () {
      positionFields(document);
      $(document.body).trigger('wc-enhanced-select-init');
    });
  });
})(jQuery);
