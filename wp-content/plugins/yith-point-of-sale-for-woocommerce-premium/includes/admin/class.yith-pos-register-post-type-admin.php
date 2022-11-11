<?php
/**
 * Register post-type admin class.
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;

if ( ! class_exists( 'YITH_POS_Register_Post_Type_Admin' ) ) {
	/**
	 * Class YITH_POS_Register_Post_Type_Admin
	 *
	 * @author Leanza Francesco <leanzafrancesco@gmail.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	class YITH_POS_Register_Post_Type_Admin {
		use YITH_POS_Singleton_Trait;

		/**
		 * YITH_POS_Register_Post_Type_Admin constructor
		 */
		public function __construct() {
			add_filter( 'get_user_option_screen_layout_' . YITH_POS_Post_Types::$register, '__return_true' );

			add_filter( 'manage_' . YITH_POS_Post_Types::$register . '_posts_columns', array( $this, 'manage_list_columns' ) );
			add_action( 'manage_' . YITH_POS_Post_Types::$register . '_posts_custom_column', array( $this, 'render_list_columns' ), 10, 2 );
			add_filter( 'default_hidden_columns', array( $this, 'default_hidden_columns' ), 10, 2 );
			add_filter( 'bulk_actions-edit-' . YITH_POS_Post_Types::$register, array( $this, 'manage_bulk_actions' ), 10, 1 );
			add_filter( 'post_row_actions', array( $this, 'manage_row_actions' ), 10, 2 );

			add_action( 'restrict_manage_posts', array( $this, 'render_filters' ), 10, 1 );
			add_action( 'pre_get_posts', array( $this, 'filter_registers' ), 10, 1 );

			add_filter( 'wp_untrash_post_status', array( $this, 'untrash_post_status' ), 10, 3 );
		}

		/**
		 * Filter registers by Store and Status
		 *
		 * @param WP_Query $query The WP query.
		 */
		public function filter_registers( $query ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( $query->is_main_query() && isset( $query->query['post_type'] ) && YITH_POS_Post_Types::$register === $query->query['post_type'] ) {
				$meta_query = ! ! $query->get( 'meta_query' ) ? $query->get( 'meta_query' ) : array();
				$changed    = false;
				if ( ! empty( $_REQUEST['store'] ) ) {
					$changed      = true;
					$meta_query[] = array(
						'key'   => '_store_id',
						'value' => absint( $_REQUEST['store'] ),
					);
				}

				if ( ! empty( $_REQUEST['status'] ) ) {
					$changed = true;
					$status  = wc_clean( wp_unslash( $_REQUEST['status'] ) );
					if ( 'closed' === $status ) {
						$meta_query[] = array(
							'relation' => 'OR',
							array(
								'key'   => '_status',
								'value' => 'closed',
							),
							array(
								'key'     => '_status',
								'compare' => 'NOT EXISTS',
							),
						);
					} else {
						$meta_query[] = array(
							'key'   => '_status',
							'value' => $status,
						);
					}
				}
				if ( $changed ) {
					$query->set( 'meta_query', $meta_query );
				}
			}

			// phpcs:enable
		}

		/**
		 * Render filters for Store and Status.
		 *
		 * @param string $post_type The post type.
		 */
		public function render_filters( $post_type ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( YITH_POS_Post_Types::$register === $post_type ) {
				$selected_store  = isset( $_REQUEST['store'] ) ? absint( $_REQUEST['store'] ) : '';
				$selected_status = isset( $_REQUEST['status'] ) ? wc_clean( wp_unslash( $_REQUEST['status'] ) ) : '';

				$store_ids   = yith_pos_get_stores();
				$store_names = array_map( 'yith_pos_get_register_name', $store_ids );
				$stores      = array_combine( $store_ids, $store_names );
				echo "<select name='store'>";
				echo "<option value=''>" . esc_html__( 'Filter by store', 'yith-point-of-sale-for-woocommerce' ) . '</option>';
				foreach ( $stores as $id => $name ) {
					echo '<option value="' . esc_attr( $id ) . '" ' . selected( $id, $selected_store, false ) . '>' . esc_html( $name ) . '</option>';
				}
				echo '</select>';

				$statuses = yith_pos_register_statuses();

				echo "<select name='status'>";
				echo "<option value=''>" . esc_html__( 'Filter by status', 'yith-point-of-sale-for-woocommerce' ) . '</option>';
				foreach ( $statuses as $id => $name ) {
					echo '<option value="' . esc_attr( $id ) . '" ' . selected( $id, $selected_status, false ) . '>' . esc_html( $name ) . '</option>';
				}
				echo '</select>';
			}
			// phpcs:enable
		}


		/**
		 * Manage the columns of the Register List
		 *
		 * @param array $columns The columns.
		 *
		 * @return array
		 */
		public function manage_list_columns( $columns ) {
			$date_text = $columns['date'];
			unset( $columns['date'] );
			unset( $columns['title'] );

			$new_columns['cb'] = $columns['cb'];
			unset( $columns['cb'] );

			$new_columns['name']    = __( 'Register Name', 'yith-point-of-sale-for-woocommerce' );
			$new_columns['store']   = __( 'Store', 'yith-point-of-sale-for-woocommerce' );
			$new_columns['info']    = __( 'Info', 'yith-point-of-sale-for-woocommerce' );
			$new_columns['status']  = __( 'Status', 'yith-point-of-sale-for-woocommerce' );
			$new_columns['enabled'] = __( 'Enabled', 'yith-point-of-sale-for-woocommerce' );

			$new_columns = array_merge( $new_columns, $columns );

			$new_columns['date'] = $date_text;

			return $new_columns;
		}

		/**
		 * Render the columns of the Register List
		 *
		 * @param array $column  Column name.
		 * @param int   $post_id Post ID.
		 */
		public function render_list_columns( $column, $post_id ) {
			$register = yith_pos_get_register( $post_id );
			switch ( $column ) {
				case 'name':
					echo '<strong>' . esc_html( $register->get_name() ) . '</strong>';
					break;
				case 'store':
					$store_id = $register->get_store_id();
					echo yith_pos_get_post_edit_link_html( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					break;

				case 'info':
					if ( $register->is_receipt_enabled() ) {
						$receipt_id = $register->get_receipt_id();
						// translators: %s is the receipt name.
						echo '<div>' . sprintf( esc_html__( 'Receipt: %s', 'yith-point-of-sale-for-woocommerce' ), yith_pos_get_post_edit_link_html( $receipt_id ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<div>' . esc_html__( 'No Receipt', 'yith-point-of-sale-for-woocommerce' ) . '</div>';
					}
					break;
				case 'status':
					$status      = $register->get_status();
					$status_name = yith_pos_get_register_status_name( $status );
					$user        = yith_pos_get_register_lock( $register->get_id() );
					echo '<span class="yith-pos-register-status yith-pos-register-status--' . esc_attr( $status ) . '">' . esc_html( $status_name ) . '</span>';

					if ( $user ) {
						echo '<div class="yith-pos-register-status__used-by">' . esc_html( yith_pos_get_employee_name( $user ) ) . '</div>';
					}
					break;
				case 'enabled':
					if ( $register->is_published() ) {
						echo "<div class='yith-plugin-ui'>";
						yith_plugin_fw_get_field(
							array(
								'type'  => 'onoff',
								'class' => 'yith-pos-register-toggle-enabled',
								'value' => $register->is_enabled() ? 'yes' : 'no',
								'data'  => array(
									'register-id' => $register->get_id(),
									'security'    => wp_create_nonce( 'register-toggle-enabled' ),
								),
							),
							true
						);
						echo '</div>';
					} else {
						$post_status     = $register->get_post_status();
						$post_status_obj = get_post_status_object( $post_status );
						echo '<div class="yith-pos-post-status yith-pos-post-status--' . esc_attr( $post_status ) . '">' . esc_html( $post_status_obj->label ) . '</div>';
					}
					break;
			}
		}

		/**
		 * Set the default hidden columns of the Register List
		 *
		 * @param array     $columns Hidden columns.
		 * @param WP_Screen $screen  Screen object.
		 *
		 * @return array
		 */
		public function default_hidden_columns( $columns, $screen ) {
			if ( 'edit-' . YITH_POS_Post_Types::$register === $screen->id ) {
				$columns[] = 'date';
			}

			return $columns;
		}

		/**
		 * Manage the bulk actions in the Register List
		 *
		 * @param array $actions Bulk Actions.
		 *
		 * @return array
		 */
		public function manage_bulk_actions( $actions ) {
			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}

			return $actions;
		}

		/**
		 * Manage the row actions in the Register List
		 *
		 * @param array   $actions Actions.
		 * @param WP_Post $post    The Post.
		 *
		 * @return array
		 */
		public function manage_row_actions( $actions, $post ) {
			if ( get_post_type( $post ) === YITH_POS_Post_Types::$register ) {
				if ( isset( $actions['inline hide-if-no-js'] ) ) {
					unset( $actions['inline hide-if-no-js'] );
				}

				if ( isset( $actions['edit'] ) ) {
					unset( $actions['edit'] );
					$register = yith_pos_get_register( $post->ID );
					if ( $register ) {
						$store_id           = $register->get_store_id();
						$edit_register_link = add_query_arg( array( 'yith-pos-edit-register' => $post->ID ), get_edit_post_link( $store_id ) );

						$actions['edit'] = "<a href='$edit_register_link'>" . esc_html__( 'Edit', 'yith-point-of-sale-for-woocommerce' ) . '</a>';
					};
				}

				$open_register_link = add_query_arg(
					array(
						'yith-pos-register-direct-login-nonce' => wp_create_nonce( 'yith-pos-register-direct-login' ),
						'register'                             => $post->ID, // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
					),
					yith_pos_get_pos_page_url()
				);

				$actions['open-register'] = "<a href='$open_register_link'>" . esc_html__( 'Open Register', 'yith-point-of-sale-for-woocommerce' ) . '</a>';
			}

			return $actions;
		}

		/**
		 * Ensure statuses are correctly set to publish when restoring registers.
		 *
		 * @param string $new_status      The new status of the post being restored.
		 * @param int    $post_id         The ID of the post being restored.
		 * @param string $previous_status The status of the post at the point where it was trashed.
		 *
		 * @return string
		 * @since 1.0.15
		 */
		public static function untrash_post_status( $new_status, $post_id, $previous_status ) {
			return 'publish';
		}
	}
}
