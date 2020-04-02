<?php
/**
 * Dintero-HP Checkout page
 *
 * Overrides /checkout/form-checkout.php.
 *
 * @package dintero-hp-checkout-for-woocommerce
 */

wc_print_notices();

do_action( 'dhp_wc_before_checkout_form' );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" class="checkout woocommerce-checkout">
<?php do_action( 'dhp_before_wrapper' ); ?>
	<div id="dhp-wrapper" class='embexp'>
		<?php do_action( 'dhp_payment_tab' ); ?>
		<div id="dhp-others">
			<div class='dhp_billing'>
				<?php do_action( 'dhp_checkout_billing' ); ?>
			</div>
			<div class='dhp_shipping'>
				<?php do_action( 'dhp_checkout_shipping' ); ?>
			</div>

			<?php woocommerce_checkout_payment(); ?>
		</div>
	</div>
	<?php do_action( 'dhp_after_wrapper' ); ?>
</form>

<?php do_action( 'dhp_after_checkout_form' ); ?>
