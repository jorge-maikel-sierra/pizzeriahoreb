<?php
/**
 * Orders Class.
 * Handle orders.
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;

if ( ! class_exists( 'YITH_POS_Orders' ) ) {
	/**
	 * Class YITH_POS_Orders
	 *
	 * @author Leanza Francesco <leanzafrancesco@gmail.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	class YITH_POS_Orders {

		use YITH_POS_Singleton_Trait;

		/**
		 * YITH_POS_Orders constructor.
		 */
		private function __construct() {
			add_action( 'woocommerce_order_item_fee_after_calculate_taxes', array( $this, 'disable_taxes_for_discounts' ), 10, 1 );
			add_action( 'woocommerce_order_item_display_meta_key', array( $this, 'order_item_meta_label' ), 10, 1 );
			add_action( 'woocommerce_payment_complete_order_status', array( $this, 'filter_order_status' ), 20, 3 );

			// The 'woocommerce_order_get_tax_location' filter requires WooCommerce 4.1 or greater.
			add_filter( 'woocommerce_order_get_tax_location', array( $this, 'order_tax_location_based_on_store_location' ), 10, 2 );
		}

		/**
		 * Filter the order tax location to calculate taxes based on store location
		 *
		 * @param array    $args  Location args.
		 * @param WC_Order $order The order.
		 *
		 * @return array
		 * @since 1.0.2
		 */
		public function order_tax_location_based_on_store_location( $args, $order ) {
			if ( yith_pos_is_pos_order( $order ) ) {
				$store_id = absint( $order->get_meta( '_yith_pos_store' ) );
				$store    = yith_pos_get_store( $store_id );
				if ( $store && $store->get_country() ) {
					$args['country']  = $store->get_country();
					$args['state']    = $store->get_state();
					$args['postcode'] = $store->get_postcode();
					$args['city']     = $store->get_city();
				}
			}

			return $args;
		}


		/**
		 * Disable taxes for discounts
		 *
		 * @param WC_Order_Item_Fee $fee The Fee.
		 */
		public function disable_taxes_for_discounts( $fee ) {
			if ( $fee->get_total() < 0 && wc_tax_enabled() && $fee->get_order() && 'discount' === $fee->get_meta( '_yith_pos_fee_type' ) ) {
				$fee->set_taxes( false );
			}
		}

		/**
		 * Filter the order item meta labels
		 *
		 * @param string $key The key.
		 *
		 * @return string
		 */
		public function order_item_meta_label( $key ) {
			$labels = array(
				'yith_pos_order_item_note' => __( 'Note', 'yith-point-of-sale-for-woocommerce' ),
			);

			return array_key_exists( $key, $labels ) ? $labels[ $key ] : $key;
		}

		/**
		 * Filter the order status for POS orders on payment complete
		 *
		 * @param string   $order_status Order status.
		 * @param int      $order_id     Order ID.
		 * @param WC_Order $order        The order.
		 *
		 * @return string
		 * @since 1.0.1
		 */
		public function filter_order_status( $order_status, $order_id, $order ) {
			if ( absint( $order->get_meta( '_yith_pos_order' ) ) ) {
				$order_status = ! ! $order->get_items( 'shipping' ) ? 'processing' : 'completed';
				$order_status = apply_filters( 'yith_pos_order_status', $order_status, $order );
			}

			return $order_status;
		}
	}
}
