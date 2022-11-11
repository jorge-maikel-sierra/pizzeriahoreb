<?php
/**
 * Products Class.
 * Handle products.
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;


if ( ! class_exists( 'YITH_POS_Products' ) ) {
	/**
	 * Class YITH_POS_Products
	 *
	 * @author Leanza Francesco <leanzafrancesco@gmail.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	class YITH_POS_Products {

		use YITH_POS_Singleton_Trait;

		/**
		 * YITH_POS_Products constructor.
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'add_new_term' ) );
			add_filter( 'woocommerce_product_visibility_options', array( $this, 'add_pos_visibility' ), 10, 1 );
			add_filter( 'woocommerce_product_get_catalog_visibility', array( $this, 'get_catalog_visibility' ), 10, 2 );
			add_filter( 'woocommerce_product_set_visibility', array( $this, 'set_product_visibility_for_new_products' ), 10, 1 );
			add_action( 'woocommerce_product_object_updated_props', array( $this, 'handle_product_visibility' ), 10, 1 );
		}

		/**
		 * Create a new term.
		 */
		public function add_new_term() {
			wp_insert_term( 'yith_pos', 'product_visibility' );
		}

		/**
		 * Add new type of visibility to the shop
		 *
		 * @param array $options Visibility options.
		 *
		 * @return array
		 */
		public function add_pos_visibility( $options ) {
			$options['yith_pos'] = __( 'POS results only', 'yith-point-of-sale-for-woocommerce' );

			return $options;
		}

		/**
		 * Filter the visibility of a product.
		 *
		 * @param string     $catalog_visibility The catalog visibility.
		 * @param WC_Product $product            The product.
		 *
		 * @return string
		 */
		public function get_catalog_visibility( $catalog_visibility, $product ) {
			return has_term( 'yith_pos', 'product_visibility', $product->get_id() ) ? 'yith_pos' : $catalog_visibility;
		}

		/**
		 * Force the visibility to yith_pos.
		 *
		 * @param int $product_id The product ID.
		 */
		public function set_product_visibility_for_new_products( $product_id ) {
			if ( isset( $_REQUEST['yith_pos_add_product'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$terms = array( 'yith_pos' );
				wp_set_post_terms( $product_id, $terms, 'product_visibility', false );
			}
		}

		/**
		 * Handle product visibility
		 *
		 * @param WC_Product $product The product.
		 *
		 * @return void
		 * @since 1.0.6
		 */
		public function handle_product_visibility( $product ) {
			if ( ! $product->is_type( 'variation' ) && 'yith_pos' === $product->get_catalog_visibility( 'edit' ) ) {
				$terms = array();

				if ( $product->get_featured() ) {
					$terms[] = 'featured';
				}

				if ( 'outofstock' === $product->get_stock_status() ) {
					$terms[] = 'outofstock';
				}

				$rating = min( 5, round( $product->get_average_rating(), 0 ) );

				if ( $rating > 0 ) {
					$terms[] = 'rated-' . $rating;
				}

				$terms[] = 'yith_pos';

				if ( ! is_wp_error( wp_set_post_terms( $product->get_id(), $terms, 'product_visibility', false ) ) ) {
					do_action( 'woocommerce_product_set_visibility', $product->get_id(), $product->get_catalog_visibility() );
				}
			}
		}
	}
}
