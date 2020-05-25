<?php
/**
 * Checkout fallback order received page, used when WC checkout form submission fails.
 *
 * Overrides /checkout/thankyou.php.
 *
 */

if ( ! WC()->session->get( 'dhp_wc_order_id' ) ) {
	return;
}

wc_empty_cart();
// Clear session storage to prevent error for customer in the future.
?>
	<script>sessionStorage.orderSubmitted = false</script>
<?php
dhp_wc_show_snippet();
