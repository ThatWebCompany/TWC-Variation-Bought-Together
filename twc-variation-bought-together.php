<?php
/**
 * Plugin Name: TWC Variation Bought Together
 * Description: Adds per-variation Bought Together selectors and integrates with Bought Together for WooCommerce (WP1).
 * Version: 1.0.5
 * Author: That Web Company
 * Requires Plugins: woocommerce, bought-together-for-woocommerce
 * Requires PHP: 7.4
 * Text Domain: twc-variation-bought-together
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TWC_Variation_Bought_Together {
    const META = '_twc_variation_bought_together_ids';
    const VERSION = '1.0.5';

    public static function init() {
        add_action( 'woocommerce_product_after_variable_attributes', [ __CLASS__, 'render_field' ], 999, 3 );
        add_action( 'woocommerce_save_product_variation', [ __CLASS__, 'save_field' ], 999, 2 );
        add_action( 'wp', [ __CLASS__, 'prepare_display' ], 20 );
        add_action( 'woocommerce_after_add_to_cart_form', [ __CLASS__, 'render_container' ], 20 );
        add_action( 'wp_ajax_twc_bt_variation_render', [ __CLASS__, 'ajax_render' ] );
        add_action( 'wp_ajax_nopriv_twc_bt_variation_render', [ __CLASS__, 'ajax_render' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'front_scripts' ], 99 );
        add_action( 'admin_notices', [ __CLASS__, 'dependency_notice' ] );
    }

    public static function dependency_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        if ( ! class_exists( 'WooCommerce' ) || ! defined( 'BOUGHT_TOGETHER_FOR_WOOCOMMERCE_VERSION' ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'TWC Variation Bought Together requires WooCommerce and the WP1 Bought Together for WooCommerce plugin to be active.', 'twc-variation-bought-together' ) . '</p></div>';
        }
    }

    public static function render_field( $loop, $variation_data, $variation ) {
        $variation_id = absint( $variation->ID );
        $saved = get_post_meta( $variation_id, self::META, true );
        $saved = is_array( $saved ) ? array_values( array_filter( array_map( 'absint', $saved ) ) ) : [];
        echo '<div class="form-row form-row-full twc-variation-bt-field" data-twc-bt-field="1">';
        echo '<p><label for="twc_variation_bt_' . esc_attr( $loop ) . '"><strong>' . esc_html__( 'Bought Together Products', 'twc-variation-bought-together' ) . '</strong></label></p>';
        echo '<select id="twc_variation_bt_' . esc_attr( $loop ) . '" class="wc-product-search" style="width:100%;" multiple="multiple" name="twc_variation_bt[' . esc_attr( $loop ) . '][]" data-placeholder="' . esc_attr__( 'Search for a product or variation…', 'twc-variation-bought-together' ) . '" data-action="woocommerce_json_search_products_and_variations" data-exclude="' . esc_attr( $variation_id ) . '">';
        foreach ( $saved as $id ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                echo '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
            }
        }
        echo '</select>';
        echo '<span class="description">' . esc_html__( 'Only these items will be offered when this variation is selected. Leave empty for no variation-specific recommendations.', 'twc-variation-bought-together' ) . '</span>';
        echo '</div>';
    }

    public static function save_field( $variation_id, $loop ) {
        if ( ! current_user_can( 'edit_product', $variation_id ) && ! current_user_can( 'edit_post', wp_get_post_parent_id( $variation_id ) ) ) { return; }
        // WooCommerce runs this hook only during its verified product variation save lifecycle.
        if ( ! isset( $_POST['twc_variation_bt'] ) || ! is_array( $_POST['twc_variation_bt'] ) ) {
            // An absent array is valid when all selector values are empty; still clear those mappings.
            update_post_meta( $variation_id, self::META, [] );
            return;
        }
        $raw = isset( $_POST['twc_variation_bt'][ $loop ] ) && is_array( $_POST['twc_variation_bt'][ $loop ] )
            ? wp_unslash( $_POST['twc_variation_bt'][ $loop ] ) : [];
        $ids = [];
        foreach ( $raw as $item ) {
            $id = absint( $item );
            $product = $id ? wc_get_product( $id ) : false;
            if ( $product && in_array( $product->get_type(), [ 'simple', 'variation' ], true ) && $product->get_status() === 'publish' && $product->is_purchasable() && $product->is_in_stock() && $id !== (int) $variation_id ) {
                $ids[] = $id;
            }
        }
        update_post_meta( $variation_id, self::META, array_values( array_unique( $ids ) ) );
    }

    private static function get_variation_ids( $parent_id ) {
        $parent = wc_get_product( $parent_id );
        if ( ! $parent || ! $parent->is_type( 'variable' ) ) { return []; }
        return $parent->get_children();
    }

    private static function linked_ids( $variation_id ) {
        $ids = get_post_meta( $variation_id, self::META, true );
        return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : [];
    }

    private static function is_managed( $parent_id ) {
        if ( ! function_exists( 'wc_get_product' ) ) { return false; }
        $product = wc_get_product( $parent_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) { return false; }
        foreach ( self::get_variation_ids( $parent_id ) as $variation_id ) {
            if ( metadata_exists( 'post', $variation_id, self::META ) ) { return true; }
        }
        return false;
    }

    public static function prepare_display() {
        if ( ! function_exists( 'is_product' ) || ! is_product() || ! self::is_managed( get_queried_object_id() ) ) {
            return;
        }
        // Disable only WP1's automatic output on products managed by this addon.
        // WP1's styles and existing Add Both to Cart handler remain active.
        $hook = get_option( 'bought_together_for_woocommerce_display_location', 'woocommerce_after_single_product_summary' );
        global $wp_filter;
        if ( ! isset( $wp_filter[ $hook ] ) ) { return; }
        foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                $fn = $callback['function'];
                if ( is_array( $fn ) && is_object( $fn[0] ) &&
                    $fn[0] instanceof Bought_Together_For_Woocommerce_Public &&
                    'bought_together_render_add_to_cart_table' === $fn[1] ) {
                    remove_action( $hook, $fn, $priority );
                }
            }
        }
    }

    public static function render_container() {
        global $product;
        if ( ! $product || ! self::is_managed( $product->get_id() ) ) { return; }
        // Elementor WooCommerce Add to Cart widgets execute this standard WC hook.
        echo '<div id="bought-together-products-data" class="bought-together-ltr twc-bt" data-id="' .
            esc_attr( $product->get_id() ) . '" data-nonce="' .
            esc_attr( wp_create_nonce( 'get-bt-table' ) ) .
            '" data-twc-nonce="' . esc_attr( wp_create_nonce( 'twc_bt_variation_render' ) ) .
            '" hidden></div>';
    }

    private static function variation_markup( $parent_id, $variation_id ) {
        $parent = wc_get_product( $parent_id );
        $selected = wc_get_product( $variation_id );
        if ( ! $parent || ! $selected || ! $selected->is_type( 'variation' ) ||
            (int) $selected->get_parent_id() !== $parent_id ||
            ! $selected->is_purchasable() || ! $selected->is_in_stock() ) {
            return '';
        }

        $ids = self::linked_ids( $variation_id );
        if ( ! $ids ) { return ''; }
        $products = [ $selected ];
        foreach ( $ids as $id ) {
            $p = wc_get_product( $id );
            if ( ! $p || ! in_array( $p->get_type(), [ 'simple', 'variation' ], true ) ||
                ! $p->is_purchasable() || ! $p->is_in_stock() ||
                'publish' !== $p->get_status() || $p->get_id() === $variation_id ) {
                continue;
            }
            $products[] = $p;
        }
        if ( count( $products ) < 2 ) { return ''; }

        $images = [];
        $lines = [];
        $total = 0;
        foreach ( $products as $index => $p ) {
            $id = $p->get_id();
            $attr = '';
            if ( $p->is_type( 'variation' ) && function_exists( 'bougth_together_get_item_attributes' ) ) {
                $attributes = bougth_together_get_item_attributes( $id );
                if ( false === $attributes ) { return ''; }
                $attr = rawurlencode( wp_json_encode( $attributes ) );
            }
            $checkbox = '<input type="checkbox" checked data-prd-id="' . esc_attr( $id ) .
                '" data-attribute="' . esc_attr( $attr ) . '" data-price="' . esc_attr( $p->get_price() ) .
                '" data-display-price="' . esc_attr( wc_get_price_to_display( $p ) ) . '">';
            $name = esc_html( $p->get_name() );
            $label = 0 === $index
                ? '<strong>' . esc_html__( 'This item', 'twc-variation-bought-together' ) . ':</strong> ' . $name
                : '<a href="' . esc_url( get_permalink( $id ) ) . '">' . $name . '</a>';
            $lines[] = '<label class="bt-list-item">' . $checkbox . ' &nbsp; ' .
                $label . ' <strong>' . wp_kses_post( $p->get_price_html() ) . '</strong></label>';
            $images[] = '<div>' . wp_kses_post( $p->get_image( 'woocommerce_gallery_thumbnail' ) ) . '</div>';
            $total += (float) wc_get_price_to_display( $p );
        }
        $title = get_post_meta( $parent_id, 'bought_together_title', true );
        if ( ! $title ) {
            $title = get_option( 'bought_together_for_woocommerce_default_title_text', 'Frequently Bought Together' );
        }
        $button_text = get_option( 'bought_together_for_woocommerce_button_two_text', 'Add both to cart' );
        if ( count( $products ) > 2 ) {
            $button_text = str_replace( '%%bt_count%%', count( $products ),
                get_option( 'bought_together_for_woocommerce_button_multi_text', 'Add all %%bt_count%% to cart' ) );
        }
        $button = '<button type="button" id="bought-together-add-to-cart" class="button alt" data-nonce="' .
            esc_attr( wp_create_nonce( 'add-bt-to-cart' ) ) . '">' . esc_html( $button_text ) . '</button>';
        return '<div style="clear:both"></div><h3>' . esc_html( $title ) . '</h3>' .
            '<div class="bt-items-container">' .
            implode( '<div class="bt-plus-icon"><div>+</div></div>', $images ) .
            '<div class="bt-add-all-button"><span>' .
            esc_html__( 'Total', 'twc-variation-bought-together' ) . ': <strong data-twc-total="1">' .
            wp_kses_post( wc_price( $total ) ) . '</strong></span><span>' . $button . '</span></div></div>' .
            '<div class="bt-list-items">' . implode( '', $lines ) . '</div><div style="clear:both"></div>';
    }

    public static function ajax_render() {
        if ( ! check_ajax_referer( 'twc_bt_variation_render', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
        }
        $parent_id = isset( $_POST['parent_id'] ) ? absint( $_POST['parent_id'] ) : 0;
        $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
        if ( ! self::is_managed( $parent_id ) ||
            (int) wp_get_post_parent_id( $variation_id ) !== $parent_id ) {
            wp_send_json_error( [ 'message' => 'Invalid variation.' ], 400 );
        }
        wp_send_json_success( [ 'html' => self::variation_markup( $parent_id, $variation_id ) ] );
    }

    public static function admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || get_post_type() !== 'product' ) { return; }
        wp_enqueue_script( 'twc-variation-bt-admin', plugins_url( 'assets/admin.js', __FILE__ ), [ 'jquery' ], self::VERSION, true );
    }

    public static function front_scripts() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ||
             ! self::is_managed( get_queried_object_id() ) ) { return; }
        wp_enqueue_script( 'twc-variation-bt-front', plugins_url( 'assets/front.js', __FILE__ ),
            [ 'jquery', 'wc-add-to-cart-variation' ], self::VERSION, true );
        wp_localize_script( 'twc-variation-bt-front', 'twcVariationBoughtTogether', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ] );
    }

}
add_action( 'plugins_loaded', [ 'TWC_Variation_Bought_Together', 'init' ], 20 );
