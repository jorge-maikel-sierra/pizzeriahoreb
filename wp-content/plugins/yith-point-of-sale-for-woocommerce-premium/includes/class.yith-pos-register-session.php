<?php
/**
 * Register Session Class.
 * Handle register sessions.
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;

if ( ! class_exists( 'YITH_POS_Register_Session' ) ) {
	/**
	 * Class YITH_POS_Register_Session
	 *
	 * @author Leanza Francesco <leanzafrancesco@gmail.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	class YITH_POS_Register_Session {

		/**
		 * Get the session object
		 *
		 * @param int  $session_id          The session ID.
		 * @param bool $cashiers_with_names Set tru to retrieve cashiers with names.
		 *
		 * @return array|object|void|null
		 */
		public static function get_session_object( $session_id, $cashiers_with_names = true ) {
			global $wpdb;

			$table_name = $wpdb->prefix . YITH_POS_DB::$register_session;

			$select = $wpdb->prepare( "SELECT * from $table_name where id=%d", $session_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$result = $wpdb->get_row( $select, OBJECT ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

			if ( $result ) {
				if ( ! is_null( $result->cash_in_hand ) ) {
					$result->cash_in_hand = maybe_unserialize( $result->cash_in_hand );
				}
				if ( ! is_null( $result->cashiers ) ) {
					if ( $cashiers_with_names ) {
						$cashiers           = maybe_unserialize( $result->cashiers );
						$cashiers_with_name = array();
						if ( $cashiers ) {
							foreach ( $cashiers as $cashier ) {
								$user = get_user_by( 'ID', $cashier['id'] );
								if ( $user ) {
									$cashier['name']      = $user->first_name . ' ' . $user->last_name;
									$cashiers_with_name[] = $cashier;
								}
							}
						}

						$result->cashiers = $cashiers_with_name;
					} else {
						$result->cashiers = maybe_unserialize( $result->cashiers );
					}
				}
			}

			return $result;
		}

		/**
		 * Create a new session inside the database.
		 *
		 * @param int $register_id Register ID.
		 *
		 * @return bool|false|int
		 */
		public static function add_session( $register_id ) {
			global $wpdb;

			$table_name = $wpdb->prefix . YITH_POS_DB::$register_session;
			$register   = new YITH_POS_Register( $register_id );
			$store_id   = $register->get_store_id();
			$now        = gmdate( 'Y-m-d H:i:s' );

			// Save the current cashier as object.
			$current_user = wp_get_current_user();

			$cashier  = array(
				'id'    => $current_user->ID,
				'login' => $now,
			);
			$cashiers = array( $cashier );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table_name,
				array(
					'store_id'    => $store_id,
					'register_id' => $register_id,
					'open'        => $now,
					'cashiers'    => serialize( $cashiers ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				),
				array( '%d', '%d', '%s', '%s' )
			);

			return $wpdb->insert_id;
		}


		/**
		 * Add the new cashier to the cashier list.
		 *
		 * @param int $session_id Session ID.
		 *
		 * @return bool|int
		 */
		public static function update_cashiers( $session_id ) {
			$session          = self::get_session_object( $session_id, false );
			$session_cashiers = ! is_null( $session->cashiers ) ? $session->cashiers : array();

			$current_user = wp_get_current_user();

			$cashier = array(
				'id'    => $current_user->ID,
				'login' => gmdate( 'Y-m-d H:i:s' ),
			);

			array_push( $session_cashiers, $cashier );

			global $wpdb;

			$table_name = $wpdb->prefix . YITH_POS_DB::$register_session;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->update(
				$table_name,
				array(
					'cashiers' => serialize( $session_cashiers ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				),
				array(
					'id' => $session_id,
				),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Close the session.
		 *
		 * @param int $session_id Session ID.
		 *
		 * @return bool|int
		 */
		public static function close_session( $session_id ) {
			global $wpdb;

			$now        = gmdate( 'Y-m-d H:i:s' );
			$table_name = $wpdb->prefix . YITH_POS_DB::$register_session;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->update(
				$table_name,
				array(
					'closed' => $now,
				),
				array(
					'id' => $session_id,
				),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Update the cash in hand array.
		 *
		 * @param int   $session_id   Session ID.
		 * @param array $cash_in_hand Cash in hand.
		 *
		 * @return bool|int
		 */
		public static function update_cash_in_hand( $session_id, $cash_in_hand ) {
			global $wpdb;

			$table_name = $wpdb->prefix . YITH_POS_DB::$register_session;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->update(
				$table_name,
				array(
					'cash_in_hand' => serialize( $cash_in_hand ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				),
				array(
					'id' => $session_id,
				),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 *
		 * Close Register
		 *
		 * @param int    $session_id Session ID.
		 * @param array  $totals     Totals.
		 * @param string $note       Note.
		 *
		 * @return bool|int
		 */
		public static function close_register( $session_id, $totals, $note ) {
			global $wpdb;
			$total = 0;
			if ( $totals ) {
				$key   = array_search( 'total', array_column( $totals, 'id' ), true );
				$total = isset( $totals[ $key ] ) ? $totals[ $key ]['amount'] : $total;
			}

			$table_name = $wpdb->prefix . YITH_POS_DB::$register_session;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->update(
				$table_name,
				array(
					'total'  => $total,
					'note'   => sanitize_text_field( $note ),
					'report' => serialize( $totals ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				),
				array(
					'id' => $session_id,
				),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}
}
