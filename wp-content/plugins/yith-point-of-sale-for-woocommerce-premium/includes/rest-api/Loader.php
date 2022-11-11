<?php
/**
 * REST API loader.
 *
 * @author  YITH
 * @package YITH\POS\RestApi
 */

namespace YITH\POS\RestApi;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 *
 * @package YITH\POS\RestApi
 */
class Loader {

	use \YITH_POS_Singleton_Trait;

	/**
	 * The server.
	 *
	 * @var Server
	 */
	private $server;

	/**
	 * Loader constructor.
	 */
	protected function __construct() {
		if ( yith_pos_is_wc_admin_enabled() ) {
			$this->load();
			$this->include_files();
			$this->init();
		}
	}

	/**
	 * Load.
	 */
	protected function load() {
		require_once 'Server.php';
		$this->server = Server::get_instance();
	}

	/**
	 * Include files.
	 */
	protected function include_files() {
		// Functions.
		require_once 'Utils/functions.php';

		// Controllers.
		$controller_files = array(
			'Version1' => array_keys( $this->server->get_v1_controllers() ),
		);

		foreach ( $controller_files as $version => $controllers ) {
			foreach ( $controllers as $controller ) {
				$filename = "class.yith-pos-wc-rest-{$controller}-controller.php";
				$path     = "Controllers/{$version}/$filename";
				require_once $path;
			}
		}

		require_once 'Reports/Orders/Stats/Controller.php';
		require_once 'Reports/Orders/Stats/DataStore.php';
		require_once 'Reports/Orders/Stats/Query.php';

		require_once 'Reports/Cashiers/Controller.php';
		require_once 'Reports/Cashiers/DataStore.php';
		require_once 'Reports/Cashiers/Query.php';

		require_once 'Reports/PaymentMethods/Controller.php';
		require_once 'Reports/PaymentMethods/DataStore.php';
		require_once 'Reports/PaymentMethods/Query.php';
	}

	/**
	 * Init.
	 */
	protected function init() {
		$this->server->init();
	}
}
