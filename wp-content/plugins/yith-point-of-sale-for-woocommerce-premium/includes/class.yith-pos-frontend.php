<?php
/**
 * Frontend Class.
 * Handle the asset registering and enqueueing.
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;

if ( ! class_exists( 'YITH_POS_Frontend' ) ) {
	/**
	 * Class YITH_POS_Frontend
	 * Main Frontend Class
	 *
	 * @author Leanza Francesco <leanzafrancesco@gmail.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	class YITH_POS_Frontend {

		use YITH_POS_Singleton_Trait;

		/**
		 * YITH_POS_Frontend constructor.
		 */
		private function __construct() {
			add_action( 'template_redirect', array( $this, 'register_login_logout_handler' ) );

			add_action( 'yith_pos_footer', array( $this, 'print_script_settings' ) );

			add_filter( 'woocommerce_rest_product_object_query', array( $this, 'extends_rest_product_query' ), 10, 2 );

			// Product search.
			add_filter( 'woocommerce_rest_product_object_query', array( $this, 'search_product_args' ), 10, 2 );
			add_filter( 'woocommerce_rest_product_object_query', array( $this, 'filter_product_on_sale' ), 10, 2 );

			// Order search.
			add_filter( 'woocommerce_rest_shop_order_object_query', array( $this, 'search_order_args' ), 10, 2 );

			// Add information to REST objects.
			add_filter( 'woocommerce_rest_prepare_product_variation_object', array( $this, 'rest_parent_categories' ), 10, 3 );
			add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'rest_parent_categories' ), 10, 3 );

			add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'rest_order_fields' ), 10, 3 );
			add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_custom_meta_to_order_api_response' ), 10, 3 );

			add_filter( 'woocommerce_rest_prepare_product_variation_object', array( $this, 'rest_product_thumbnails' ), 10, 3 );
			add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'rest_product_thumbnails' ), 10, 3 );

			// Customer VAT field.
			add_filter( 'woocommerce_billing_fields', array( $this, 'add_billing_vat' ) );
			add_filter( 'woocommerce_rest_pre_insert_shop_order_object', array( $this, 'save_billing_vat_in_rest' ), 10, 2 );

			// POS sub-pages.
			add_action( 'init', array( $this, 'pos_page_rewrite' ), 0 );
			add_filter( 'option_rewrite_rules', array( $this, 'rewrite_rules' ), 1 );
		}

		/**
		 * Filter the product on sale.
		 * This method has been written because WC add all products as post_in without handling the excluded products.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 *
		 * @return array
		 */
		public function filter_product_on_sale( $args, $request ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended

			if ( isset( $_GET['yith_on_sale'] ) ) {
				$on_sale_ids      = wc_get_product_ids_on_sale();
				$exclude          = isset( $_GET['exclude'] ) ? array_map( 'absint', explode( ',', wc_clean( wp_unslash( $_GET['exclude'] ) ) ) ) : array();
				$args['post__in'] = array_diff( $on_sale_ids, $exclude );
				unset( $args['post__not_in'] );
			}

			// phpcs:enable

			return $args;
		}

		/**
		 * Hook into order API response to add custom field data.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Order         $order    The order.
		 * @param WP_REST_Request  $request  Request object.
		 *
		 * @return WP_REST_Response
		 */
		public function add_custom_meta_to_order_api_response( $response, $order, $request ) {
			if ( $order ) {
				$data                             = $response->get_data();
				$data['multiple_payment_methods'] = yith_pos_get_order_payment_methods( $order );
				$data['pos_payment_details']      = yith_pos_get_order_payment_details( $order );
				$response->set_data( $data );
			}

			return $response;
		}

		/**
		 * Get rewrite rule regex for the POS sub-pages.
		 *
		 * @return string
		 * @since 1.0.20
		 */
		private function get_pos_subpages_rewrite_rule_regex() {
			static $regex = null;
			if ( is_null( $regex ) ) {
				$base_url = $this->get_base_url();
				$regex    = '^' . $base_url . '([^/]*)';
			}

			return $regex;
		}

		/**
		 * Check if the permalink should be flushed.
		 *
		 * @param array $rules Rewrite rules.
		 *
		 * @return bool
		 */
		public function rewrite_rules( $rules ) {
			return isset( $rules[ $this->get_pos_subpages_rewrite_rule_regex() ] ) ? $rules : false;
		}

		/**
		 * All the child-pages of pos will open the pos page without change the current URL
		 */
		public function pos_page_rewrite() {
			$pos_page_id = yith_pos_get_pos_page_id();
			$regex       = $this->get_pos_subpages_rewrite_rule_regex();
			add_rewrite_rule( $regex, 'index.php?page_id=' . $pos_page_id, 'top' );

		}


		/**
		 * Add billing VAT field.
		 *
		 * @param array $address_fields Address fields.
		 *
		 * @return mixed
		 */
		public function add_billing_vat( $address_fields ) {
			$address_fields['billing_vat'] = array(
				'label'    => __( 'VAT', 'yith-point-of-sale-for-woocommerce' ),
				'required' => false,
				'type'     => 'text',
				'class'    => array( 'form-row-wide' ),
				'priority' => 35,
			);

			return $address_fields;
		}

		/**
		 * Save VAT info in billing address when order is created via REST API
		 *
		 * @param WC_Order        $order   The Order.
		 * @param WP_REST_Request $request Request object.
		 *
		 * @return WC_Order
		 * @since 1.0.8
		 */
		public function save_billing_vat_in_rest( $order, $request ) {
			if ( yith_pos_is_pos_order( $order ) && isset( $request['billing'] ) && ! empty( $request['billing']['vat'] ) ) {
				$order->update_meta_data( '_billing_vat', $request['billing']['vat'] );
			}

			return $order;
		}

		/**
		 * Add parent_categories to product variations (for coupon check).
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Product       $product  The product.
		 * @param WP_REST_Request  $request  Request object.
		 *
		 * @return WP_REST_Response
		 */
		public function rest_parent_categories( $response, $product, $request ) {
			if ( $product && $product->is_type( 'variation' ) ) {
				$variable   = wc_get_product( $product->get_parent_id() );
				$categories = array();
				if ( $variable ) {
					$categories = $this->get_taxonomy_terms( $variable );
				}
				$data                      = $response->get_data();
				$data['parent_categories'] = $categories;
				$response->set_data( $data );
			}

			return $response;
		}

		/**
		 *
		 * Set product thumbnails in REST response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Product       $product  The product.
		 * @param WP_REST_Request  $request  Request object.
		 *
		 * @return WP_REST_Response
		 */
		public function rest_product_thumbnails( $response, $product, $request ) {
			if ( $product ) {
				$data  = $response->get_data();
				$image = $product->is_type( 'variation' ) ? yith_pos_rest_get_product_thumbnail( $product->get_parent_id(), $product->get_id() ) : yith_pos_rest_get_product_thumbnail( $product->get_id() );

				if ( $image ) {
					$data['yithPosImage'] = $image;
					$response->set_data( $data );
				}
			}

			return $response;
		}

		/**
		 * Add fields in order REST response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WC_Order         $order    The order.
		 * @param WP_REST_Request  $request  Request object.
		 *
		 * @return WP_REST_Response
		 */
		public function rest_order_fields( $response, $order, $request ) {
			if ( $order ) {
				$data = $response->get_data();

				// Add item thumbnails.
				if ( isset( $data['line_items'] ) ) {
					foreach ( $data['line_items'] as &$line_item ) {
						$variation_id              = ! empty( $line_item['variation_id'] ) ? $line_item['variation_id'] : 0;
						$product_id                = $line_item['product_id'];
						$line_item['yithPosImage'] = yith_pos_rest_get_product_thumbnail( $product_id, $variation_id );
					}
				}

				$info        = array();
				$store_id    = $order->get_meta( '_yith_pos_store' );
				$register_id = $order->get_meta( '_yith_pos_register' );
				$cashier_id  = $order->get_meta( '_yith_pos_cashier' );

				if ( $store_id ) {
					$info['store_name'] = yith_pos_get_store_name( $store_id );
				}

				if ( $register_id ) {
					$info['register_name'] = yith_pos_get_register_name( $register_id );
				}

				if ( $cashier_id ) {
					$info['cashier_name'] = yith_pos_get_employee_name( $cashier_id, array( 'hide_nickname' => true ) );
				}

				if ( $info ) {
					$data['yith_pos_data'] = $info;
				}

				$response->set_data( $data );
			}

			return $response;
		}

		/**
		 * Retrieve taxonomy terms
		 *
		 * @param WC_Product $product  The product.
		 * @param string     $taxonomy The taxonomy.
		 *
		 * @return array
		 */
		protected function get_taxonomy_terms( $product, $taxonomy = 'cat' ) {
			$terms = array();

			foreach ( wc_get_object_terms( $product->get_id(), 'product_' . $taxonomy ) as $term ) {
				$terms[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}

			return $terms;
		}


		/**
		 * Extends REST product query.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 *
		 * @return array
		 */
		public function extends_rest_product_query( $args, $request ) {
			if ( isset( $request['yith_pos_stock_status'] ) ) {
				$stock_statuses = explode( ',', $request['yith_pos_stock_status'] );
				$meta_query     = array(
					'key'     => '_stock_status',
					'value'   => $stock_statuses,
					'compare' => 'IN',
				);
				if ( isset( $args['meta_query'] ) ) {
					$args['meta_query'][] = $meta_query;
				} else {
					$args['meta_query'] = array( $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}
			}

			if ( isset( $request['exclude_category'] ) ) {
				$stock_statuses = explode( ',', $request['exclude_category'] );
				$tax_query      = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $stock_statuses,
					'operator' => 'NOT IN',
				);
				if ( isset( $args['tax_query'] ) ) {
					$args['tax_query'][] = $tax_query;
				} else {
					$args['tax_query'] = array( $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				}
			}

			return $args;
		}

		/**
		 * Extend search product to sku for product and product variation.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 *
		 * @return array
		 */
		public function search_product_args( $args, $request ) {
			global $wpdb;

			// phpcs:disable WordPress.Security.NonceVerification.Recommended

			if ( isset( $_GET['queryName'], $_GET['barcode'], $args['s'] ) && 'yith_pos_search' === $_GET['queryName'] && 'sku' !== $_GET['barcode'] ) {
				$is_custom_barcode_search = yith_plugin_fw_is_true( wc_clean( wp_unslash( $_GET['barcode'] ) ) );
				$include_variations       = apply_filters( 'yith_pos_search_include_variations', $is_custom_barcode_search, $args, $request );
				$include_searching_by_sku = apply_filters( 'yith_pos_search_include_searching_by_sku', false, $args, $request );
				$per_page                 = apply_filters( 'yith_pos_search_products_per_page', $args['posts_per_page'] ?? 9 );

				if ( $include_variations ) {
					add_filter( 'pre_get_posts', array( $this, 'filter_query_post_type' ), 10 );
				}

				if ( $is_custom_barcode_search ) {
					$barcode_meta = yith_pos_get_barcode_meta();
					$search       = esc_sql( trim( $args['s'] ) );
					$query        = $wpdb->prepare(
						"SELECT p.ID FROM $wpdb->posts p
                            LEFT JOIN $wpdb->postmeta pm2 ON ( pm2.post_id = p.ID AND pm2.meta_key = %s )
                            WHERE p.post_type in('product', 'product_variation') AND p.post_status = 'publish'
                            AND pm2.meta_key = %s AND pm2.meta_value = %s
                            GROUP BY p.ID LIMIT %d",
						$barcode_meta,
						$barcode_meta,
						$search,
						$per_page
					);
					$query        = apply_filters( 'yith_pos_query_custom_barcode_search', $query, $barcode_meta, $search, $per_page );
				} elseif ( $include_variations || $include_searching_by_sku ) {
					$title_search  = apply_filters( 'yith_pos_search_by_title_arg', '%' . esc_sql( $args['s'] ) . '%', $args['s'] );
					$use_exact_sku = ! ! apply_filters( 'yith_pos_search_use_exact_sku', true, $args, $request );

					$sku_search = esc_sql( $args['s'] );
					if ( ! $use_exact_sku ) {
						$sku_search = '%' . $sku_search . '%';
					}
					$sku_search = apply_filters( 'yith_pos_search_by_sku_arg', $sku_search, $args['s'] );

					$join  = '';
					$where = $include_variations ? "p.post_type in ('product', 'product_variation')" : "p.post_type = 'product'";

					$where .= " AND p.post_status = 'publish' ";

					$limit = $wpdb->prepare( 'LIMIT %d', $per_page );

					if ( $include_searching_by_sku ) {
						$join .= " LEFT JOIN $wpdb->postmeta pm1 ON ( pm1.post_id = p.ID) ";
						if ( $use_exact_sku ) {
							$where .= $wpdb->prepare( " AND ( p.post_title LIKE %s OR ( pm1.meta_key = '_sku' AND pm1.meta_value = %s ) ) ", $title_search, $sku_search );
						} else {
							$where .= $wpdb->prepare( " AND ( p.post_title LIKE %s OR ( pm1.meta_key = '_sku' AND pm1.meta_value LIKE %s ) ) ", $title_search, $sku_search );
						}
					} else {
						$where .= $wpdb->prepare( ' AND p.post_title LIKE %s ', $title_search );
					}

					$query = "SELECT DISTINCT p.ID FROM $wpdb->posts p {$join} WHERE {$where} {$limit}";
				} else {
					// Use the standard WooCommerce Search through REST API.
					$query = false;
					$args['posts_per_page'] = $per_page;
				}

				if ( $query ) {
					$results = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

					if ( $results ) {
						$args['post__in'] = $results;
						unset( $args['s'] );
					}
				}
			}

			// phpcs:enable

			return $args;
		}


		/**
		 * Filter the orders for the store.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 *
		 * @return array
		 */
		public function search_order_args( $args, $request ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['queryName'] ) && 'yith_pos_search_orders' === $_GET['queryName'] ) {
				$meta_query = array(
					'relation' => 'AND',
					array(
						'key'     => '_yith_pos_order',
						'value'   => '1',
						'compare' => '=',
					),
				);

				$store_id    = isset( $_GET['store'] ) ? absint( $_GET['store'] ) : false;
				$register_id = isset( $_GET['register'] ) ? absint( $_GET['register'] ) : false;
				$cashier_id  = isset( $_GET['cashier'] ) ? absint( $_GET['cashier'] ) : false;

				if ( $store_id ) {
					$meta_query[] = array(
						'key'     => '_yith_pos_store',
						'value'   => $store_id,
						'compare' => '=',
					);
				}

				if ( $register_id ) {
					$meta_query[] = array(
						'key'     => '_yith_pos_register',
						'value'   => $register_id,
						'compare' => '=',
					);
				}

				if ( $cashier_id ) {
					$meta_query[] = array(
						'key'     => '_yith_pos_cashier',
						'value'   => $cashier_id,
						'compare' => '=',
					);
				}

				$args['meta_query'] = apply_filters( 'yith_pos_search_order_meta_query', $meta_query, $request ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}

			// phpcs:enable

			return $args;
		}

		/**
		 * Extend the query also for product variation.
		 *
		 * @param WP_Query $query The WP query object.
		 */
		public function filter_query_post_type( $query ) {
			$query->query_vars['post_type'] = array( 'product', 'product_variation' );
		}


		/**
		 * Handle login/logout for POS registers.
		 */
		public function register_login_logout_handler() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			if ( is_yith_pos() ) {

				$register_id = isset( $_POST['register'] ) ? absint( $_POST['register'] ) : yith_pos_register_logged_in();

				if ( isset( $_GET['yith-pos-register-direct-login-nonce'], $_GET['register'] ) ) {
					$register_id = absint( $_GET['register'] );
				}
				if ( $register_id && ! isset( $_GET['user-editing'] ) && ! isset( $_GET['yith-pos-take-over-nonce'] ) ) {
					$user_editing = yith_pos_check_register_lock( $register_id );
					if ( $user_editing ) {
						// Another user is managing the register.
						$args = array(
							'user-editing' => $user_editing,
							'register'     => $register_id,
							'store'        => isset( $_POST['store'] ) ? absint( $_POST['store'] ) : '',
						);
						wp_safe_redirect( add_query_arg( $args, yith_pos_get_pos_page_url() ) );
						exit;
					}
				}
				$action      = '';
				$register_id = false;
				$redirect    = false;

				if ( isset( $_POST['yith-pos-register-login-nonce'], $_POST['register'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_POST['yith-pos-register-login-nonce'] ) ), 'yith-pos-register-login' ) ) {
					$action      = 'login';
					$register_id = absint( $_POST['register'] );
					$redirect    = yith_pos_get_pos_page_url();
				} elseif ( isset( $_GET['yith-pos-register-direct-login-nonce'], $_GET['register'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['yith-pos-register-direct-login-nonce'] ) ), 'yith-pos-register-direct-login' ) ) {
					$action      = 'direct-login';
					$register_id = absint( $_GET['register'] );
					$redirect    = yith_pos_get_pos_page_url();
				} elseif ( isset( $_GET['yith-pos-take-over-nonce'], $_GET['register'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['yith-pos-take-over-nonce'] ) ), 'yith-pos-take-over' ) ) {
					$action      = 'take-over';
					$register_id = absint( $_GET['register'] );
					$redirect    = yith_pos_get_pos_page_url();
				} elseif ( isset( $_GET['yith-pos-register-close-nonce'], $_GET['register'] ) && wp_verify_nonce( wc_clean( wp_unslash( $_GET['yith-pos-register-close-nonce'] ) ), 'yith-pos-register-close-' . absint( $_GET['register'] ) ) ) {
					$action      = 'close-register';
					$register_id = absint( $_GET['register'] );
					// TODO: redirect to a specific page to show the report for closing register.
					$redirect = yith_pos_get_pos_page_url();
				} elseif ( ! empty( $_GET['yith-pos-user-logout'] ) ) {
					$action   = 'logout';
					$redirect = yith_pos_get_pos_page_url();
				} elseif ( ! empty( $_GET['yith-pos-register-logout'] ) ) {
					$action   = 'register-logout';
					$redirect = yith_pos_get_pos_page_url();
				}

				if ( $register_id && ! yith_pos_user_can_use_register( $register_id ) ) {
					wp_die( esc_html__( 'Error: you cannot get access to this Register!', 'yith-point-of-sale-for-woocommerce' ) );
				}

				switch ( $action ) {
					case 'login':
					case 'direct-login':
					case 'take-over':
						if ( $register_id ) {
							yith_pos_maybe_open_register( $register_id );
							yith_pos_set_register_lock( $register_id );
							yith_pos_register_login( $register_id );
						}
						break;

					case 'close-register':
						if ( $register_id ) {
							yith_pos_close_register( $register_id );
						} // no break // Please DON'T break me, since I need to logout.
					case 'register-logout':
					case 'logout':
						$register_id = yith_pos_register_logged_in();
						if ( $register_id ) {
							yith_pos_unset_register_lock( $register_id );
						}
						yith_pos_register_logout();

						if ( 'logout' === $action ) {
							wp_logout();
						}
						break;
				}

				if ( $redirect ) {
					wp_safe_redirect( $redirect );
					exit;
				}
			}
			// phpcs:enable
		}

		/**
		 * Print script settings.
		 */
		public function print_script_settings() {
			$settings = yith_pos_settings()->get_frontend_settings();
			if ( $settings ) {
				?>
				<script type="text/javascript">
					var yithPosSettings = yithPosSettings || JSON.parse( decodeURIComponent( '<?php echo rawurlencode( wp_json_encode( $settings ) ); ?>' ) );
				</script>
				<?php
			}
		}

		/**
		 * Get base URL for POS page.
		 *
		 * @return string
		 */
		public function get_base_url() {
			$pos_url  = yith_pos_get_pos_page_url();
			$site_url = strtok( get_site_url(), '?' );
			$base_url = str_replace( $site_url, '', $pos_url );
			$start    = stripos( $base_url, '/' );
			$base_url = 0 === $start ? substr( $base_url, 1 ) : $base_url;

			return $base_url;
		}

	}
}

if ( ! function_exists( 'yith_pos_frontend' ) ) {
	/**
	 * Unique access to instance of YITH_POS_Frontend class
	 *
	 * @return YITH_POS_Frontend
	 */
	function yith_pos_frontend() {
		return YITH_POS_Frontend::get_instance();
	}
}
