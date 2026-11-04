/* global jQuery, twcVariationBoughtTogether */
(function ($) {
  'use strict';
  $(function () {
    var $box = $('#bought-together-products-data.twc-bt');
    if (!$box.length) return;

    var $form = $('form.variations_form').first();
    var sequence = 0;
    var currentVariation = 0;

    function chosenVariation() {
      return parseInt($form.find('input.variation_id').val(), 10) || 0;
    }

    function hide() {
      $box.attr('hidden', 'hidden').hide();
    }

    function updateTotal() {
      var sum = 0;
      var selected = 0;
      $box.find('input[type="checkbox"][data-prd-id]:checked').each(function () {
        sum += parseFloat($(this).attr('data-display-price')) || 0;
        selected++;
      });
      var formatted = new Intl.NumberFormat('en-AU', {
        style: 'currency', currency: 'AUD'
      }).format(sum);
      $box.find('[data-twc-total]').text(formatted);
      $box.find('#bought-together-add-to-cart').prop('disabled', !selected);
    }

    function render(variationId) {
      variationId = parseInt(variationId, 10) || 0;
      currentVariation = variationId;
      var requestId = ++sequence;
      hide();
      if (!variationId) {
        $box.empty();
        return;
      }

      $.ajax({
        url: twcVariationBoughtTogether.ajaxUrl,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'twc_bt_variation_render',
          parent_id: $box.attr('data-id'),
          variation_id: variationId,
          nonce: $box.attr('data-twc-nonce')
        }
      }).done(function (response) {
        if (requestId !== sequence || chosenVariation() !== variationId) return;
        var html = response && response.success && response.data ? response.data.html : '';
        if (!html || !String(html).trim()) {
          $box.empty();
          hide();
          return;
        }
        $box.html(html);
        $box.removeAttr('hidden').show();
      }).fail(function () {
        if (requestId === sequence) {
          $box.empty();
          hide();
        }
      });
    }

    // WP1 invokes this method after variation selections and when its
    // checkboxes change. Preserve its Add Both to Cart listener but replace
    // only the old parent-metadata-driven refresh path.
    $.fn.refreshBoughtTogetherTableData = function (_checked, updateState, variationId) {
      if (updateState) {
        updateTotal();
      } else {
        render(variationId || chosenVariation());
      }
      return this;
    };

    // WooCommerce fires these for both normal variation forms and Elementor's
    // product add-to-cart widget.
    $form.on('found_variation.twcBt show_variation.twcBt', function (_event, variation) {
      var id = parseInt(variation && variation.variation_id, 10) || chosenVariation();
      if (id && id !== currentVariation) render(id);
    });
    $form.on('reset_data.twcBt hide_variation.twcBt', function () {
      currentVariation = 0;
      sequence++;
      $box.empty();
      hide();
    });
    $box.on('change.twcBt', 'input[type="checkbox"][data-prd-id]', updateTotal);

    // WP1 adds the bundle through its own AJAX endpoint, bypassing Elementor
    // Pro's normal add-to-cart event. Only handle successful bundle responses.
    function openElementorMenuCart() {
      var $drawer = $('.elementor-menu-cart__main').first();
      if (!$drawer.length || $drawer.attr('aria-hidden') === 'false') return;

      // Use Elementor's own toggle rather than mutating drawer CSS or classes.
      var $toggle = $('.elementor-menu-cart__toggle_button, #elementor-menu-cart__toggle_button')
        .filter(':visible').first();
      if (!$toggle.length) {
        $toggle = $('.elementor-menu-cart__toggle_button, #elementor-menu-cart__toggle_button').first();
      }
      if ($toggle.length) $toggle.trigger('click');
    }

    $(document).ajaxComplete(function (_event, xhr, settings) {
      var data = settings && settings.data;
      if (!data || typeof data !== 'string' ||
          !/(?:^|&)action=bought_together_add_to_cart(?:&|$)/.test(data)) {
        return;
      }
      if (xhr.status < 200 || xhr.status >= 300) return;

      var result;
      try { result = JSON.parse(xhr.responseText); }
      catch (_error) { return; }
      if (!result || typeof result.content !== 'string' || result.redirect) return;

      // WP1 returns HTML notices rather than a WooCommerce cart JSON response.
      // Only a positive add-to-cart notice authorizes opening the drawer.
      var noticeText = $('<div>').html(result.content).text();
      if (/something went wrong/i.test(noticeText) ||
          !/\b[1-9]\d*\s+product\(s\) added to cart\b/i.test(noticeText)) return;

      // Subscribe before requesting fresh fragments so Elementor's mini-cart
      // reflects both items when its drawer is opened.
      var $body = $(document.body);
      $body.one('wc_fragments_refreshed.twcBtCart', openElementorMenuCart);
      $body.trigger('wc_fragment_refresh');
    });

    hide();
    var initial = chosenVariation();
    if (initial) render(initial);
  });
})(jQuery);
