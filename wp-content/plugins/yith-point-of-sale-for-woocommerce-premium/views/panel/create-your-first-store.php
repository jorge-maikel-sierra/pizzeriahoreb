<?php
/**
 * Create your first store.
 *
 * @author  YITH
 * @package YITH\POS\Views
 */

defined( 'YITH_POS' ) || exit();

?>
<div id="yith-pos-create-your-first-store" class='yith-plugin-fw-panel-custom-tab-container'>
	<div id="yith-pos-create-your-first-store__image"><img src="<?php echo esc_url( YITH_POS_ASSETS_URL . '/images/store-register.png' ); ?>"/></div>
	<div id="yith-pos-create-your-first-store__message">
		<?php
		echo implode(
			'<br />',
			array(
				// translators: %s is the plugin name.
				sprintf( esc_html__( 'Thanks for choosing %s!', 'yith-point-of-sale-for-woocommerce' ), '<strong>' . esc_html( YITH_POS_PLUGIN_NAME ) . '</strong>' ),
				esc_html__( 'Now, the first step is to create a Store: after that, you will be able to use our powerful Register to sell your products.', 'yith-point-of-sale-for-woocommerce' ),
			)
		);
		?>
	</div>

	<div id="yith-pos-create-your-first-store__call-to-action">
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . YITH_POS_Post_Types::$store ) ); ?>" id="yith-pos-create-your-first-store__button" class="yith-pos-admin-hero-button"><?php esc_html_e( 'Create your first store', 'yith-point-of-sale-for-woocommerce' ); ?></a>
	</div>
</div>

</div>
<div class="hidden">
