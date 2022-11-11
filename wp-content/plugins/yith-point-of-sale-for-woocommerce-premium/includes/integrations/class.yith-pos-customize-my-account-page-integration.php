<?php
/**
 * "Customize My Account Page" integration class.
 *
 * @author  YITH
 * @package YITH\POS\Classes
 */

defined( 'YITH_POS' ) || exit;

/**
 * Class YITH_POS_Customize_My_Account_Page_Integration
 *
 * @author  Leanza Francesco <leanzafrancesco@gmail.com>
 * @since   1.0.6
 */
class YITH_POS_Customize_My_Account_Page_Integration extends YITH_POS_Integration {
	/**
	 * YITH_POS_Customize_My_Account_Page_Integration constructor.
	 */
	protected function __construct() {
		parent::__construct();

		add_filter( 'ywcmap_skip_verification', array( $this, 'filter_skip_verification' ), 10, 1 );
	}

	/**
	 * Maybe skip verification.
	 *
	 * @param string $verification Verification flag.
	 *
	 * @return string
	 */
	public function filter_skip_verification( $verification ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && isset( $_GET['yith_pos_add_customer'] ) && ! ! $_GET['yith_pos_add_customer'] ) {
			$verification = 'yes';
		}

		return $verification;
	}

}
