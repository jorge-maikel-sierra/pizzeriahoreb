<?php
/**
 * Database Class
 * handle DB custom tables
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;

if ( ! class_exists( 'YITH_POS_DB' ) ) {
	/**
	 * Class YITH_POS_DB
	 * handle DB custom tables
	 *
	 * @abstract
	 * @author Leanza Francesco <leanzafrancesco@gmail.com>
	 * @author Emanuela Castorina <emanuela.castorina@yithemes.com>
	 */
	abstract class YITH_POS_DB {

		/**
		 * DB version
		 *
		 * @var string
		 */
		public static $version = '1.0.0';

		/**
		 * Register Session Table name.
		 *
		 * @var string
		 */
		public static $register_session = 'yith_pos_register_sessions';

		/**
		 * Install
		 */
		public static function install() {
			self::create_db_tables();
		}

		/**
		 * Create tables
		 *
		 * @param bool $force Set true to force creation.
		 */
		public static function create_db_tables( $force = false ) {
			global $wpdb;

			$current_version = get_option( 'yith_pos_db_version' );

			if ( $force || $current_version !== self::$version ) {
				$wpdb->hide_errors();

				$register_session_table_name = $wpdb->prefix . self::$register_session;
				$charset_collate             = $wpdb->get_charset_collate();

				$sql
					= "CREATE TABLE $register_session_table_name (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `store_id` bigint(20) NOT NULL,
                    `register_id` bigint(20) NOT NULL,
                    `open` datetime NOT NULL,
                    `closed` datetime,
                    `cashiers` longtext,
                    `total` varchar(255),
                    `cash_in_hand` longtext,
                    `note` varchar(255),
                    `report` longtext,
                    PRIMARY KEY (id)
                    ) $charset_collate;";

				if ( ! function_exists( 'dbDelta' ) ) {
					require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				}
				dbDelta( $sql );
				update_option( 'yith_pos_db_version', self::$version );
			}
		}
	}
}
