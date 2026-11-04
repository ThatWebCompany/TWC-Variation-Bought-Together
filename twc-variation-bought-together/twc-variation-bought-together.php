<?php
/**
 * Plugin Name: TWC Variation Bought Together
 * Description: Adds per-variation Bought Together selectors and integrates with Bought Together for WooCommerce (WP1).
 * Version: 1.0.0
 * Author: That Web Company
 * Requires Plugins: woocommerce, bought-together-for-woocommerce
 * Requires PHP: 7.4
 * Text Domain: twc-variation-bought-together
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TWC_Variation_Bought_Together {
    const META = '_twc_variation_bought_together_ids';
    const VERSION = '1.0.0';

    public static function init() {
        add_action( 'woocommerce_product_after_variable_attributes', [ __CLASS__, 'render_field' ], 999, 3 );
        add_action( 'woocommerce_save_product_variation', [ __CLASS__, 'save_field' ], 999, 2 );
        add_filter( 'get_post_metadata', [ __CLASS__, 'filtered_recommendations' ], 10, 4 );
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

    public static function filtered_recommendations( $value, $object_id, $meta_key, $single ) {
        if ( 'bought_together_ids' !== $meta_key || ! function_exists( 'wc_get_product' ) || is_admin() && ! wp_doing_ajax() ) { return $value; }
        $parent_id = absint( $object_id );
        $product = wc_get_product( $parent_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) { return $value; }
        $variations = self::get_variation_ids( $parent_id );
        if ( ! $variations ) { return $value; }

        // Only modify the WP1 front-end AJAX table-refresh action (never its admin saves).
        if ( wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
            if ( 'bought_together_get_table_data' !== $action ) { return $value; }
            $requested_parent = isset( $_POST['_pid'] ) ? absint( $_POST['_pid'] ) : 0;
            if ( $requested_parent !== $parent_id ) { return $value; }
            $variation_id = isset( $_POST['_varid'] ) ? absint( $_POST['_varid'] ) : 0;
            if ( ! $variation_id || ! in_array( $variation_id, $variations, true ) ) { return $value; }
            $ids = self::linked_ids( $variation_id );
            return $single ? $ids : [ $ids ];
        }

        // On the product page provide a seed list so WP1 renders its AJAX container even
        // when the parent product has no manually assigned Bought Together products.
        if ( ! function_exists( 'is_product' ) || ! is_product() || (int) get_queried_object_id() !== $parent_id ) { return $value; }
        $seed = [];
        foreach ( $variations as $variation_id ) {
            $ids = self::linked_ids( $variation_id );
            if ( $ids ) { $seed = $ids; break; }
        }
        if ( ! $seed ) { return $value; }
        return $single ? $seed : [ $seed ];
    }

    public static function admin_scripts( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || get_post_type() !== 'product' ) { return; }
        wp_enqueue_script( 'twc-variation-bt-admin', plugins_url( 'assets/admin.js', __FILE__ ), [ 'jquery' ], self::VERSION, true );
    }

    public static function front_scripts() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) { return; }
        $product = wc_get_product( get_queried_object_id() );
        if ( ! $product || ! $product->is_type( 'variable' ) ) { return; }
        $has_mapping = false;
        foreach ( self::get_variation_ids( $product->get_id() ) as $variation_id ) {
            if ( self::linked_ids( $variation_id ) ) { $has_mapping = true; break; }
        }
        if ( ! $has_mapping ) { return; }
        wp_enqueue_script( 'twc-variation-bt-front', plugins_url( 'assets/front.js', __FILE__ ), [ 'jquery' ], self::VERSION, true );
    }
}
add_action( 'plugins_loaded', [ 'TWC_Variation_Bought_Together', 'init' ], 20 );
