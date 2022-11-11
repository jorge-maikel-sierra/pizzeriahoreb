<?php
/**
 * Order POS info meta-box.
 *
 * @var string $store_name      The store name.
 * @var string $register_name   The register name.
 * @var string $cashier         The cashier name.
 * @var array  $payment_methods Payment methods.
 * @var string $currency        Order currency.
 *
 * @author  YITH
 * @package YITH\POS\Views
 */

defined( 'YITH_POS' ) || exit();

?>
<div class="yith-pos-info">
	<h4><?php esc_html_e( 'Order made in store:', 'yith-point-of-sale-for-woocommerce' ); ?></h4>
	<p><?php echo esc_html( $store_name ); ?></p>
</div>

<div class="yith-pos-info">
	<h4><?php esc_html_e( 'Register:', 'yith-point-of-sale-for-woocommerce' ); ?></h4>
	<p><?php echo esc_html( $register_name ); ?></p>
</div>

<div class="yith-pos-info">
	<h4><?php esc_html_e( 'Cashier:', 'yith-point-of-sale-for-woocommerce' ); ?></h4>
	<p><?php echo esc_html( $cashier ); ?></p>
</div>

<?php if ( $payment_methods ) : ?>
	<?php
	$gateways = WC()->payment_gateways()->payment_gateways();
	?>
	<div class="yith-pos-info">
		<h4><?php esc_html_e( 'Payment methods:', 'yith-point-of-sale-for-woocommerce' ); ?></h4>

		<?php foreach ( $payment_methods as $payment_method ) : ?>
			<?php if ( isset( $gateways[ $payment_method->paymentMethod ] ) ) : // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase ?>
				<?php
				$gateway_name = $gateways[ $payment_method->paymentMethod ]->title; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				?>
				<div class="payment-method"><span class="title"><?php echo esc_html( $gateway_name ); ?></span><span
							class="amount">
							<?php
							echo wc_price( $payment_method->amount, $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</span></div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
<?php endif ?>
