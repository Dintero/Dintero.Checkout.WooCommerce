<?php
/**
 * Admin View: Settings
 *
 * @package WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_tab = 'dintero-hp';
/*
$tab_exists        = isset( $tabs[ $current_tab ] ) || has_action( 'woocommerce_sections_' . $current_tab ) || has_action( 'woocommerce_settings_' . $current_tab ) || has_action( 'woocommerce_settings_tabs_' . $current_tab );
$current_tab_label = isset( $tabs[ $current_tab ] ) ? $tabs[ $current_tab ] : '';

if ( ! $tab_exists ) {
	wp_safe_redirect( admin_url( 'admin.php?page=wc-dintero-settings' ) );
	exit;
}*/
?>
<div class="wrap woocommerce">
	<form method="post" id="mainform" action="" enctype="multipart/form-data">
		<h1 class="screen-reader-text2"><?php echo esc_html( 'Dintero Checkout Settings' ); ?></h1>
		
		<h3>Connect eCommerce with Dintero:</h3>
		
		<ol>
			<li>Create Dintero account at www.dintero.com</li>
			<li>Login to Dintero Backoffice and click on settings -> API Clients</li>
			<li>Create new API Client</li>
		</ol>

		<?php
			// do_action( 'woocommerce_sections_' . $current_tab );
			self::show_messages();

			do_action( 'woocommerce_settings_' . $current_tab );
			do_action( 'woocommerce_settings_tabs_' . $current_tab ); // @deprecated hook. @todo remove in 4.0.
		?>
		<p class="submit">
			<?php if ( empty( $GLOBALS['hide_save_button'] ) ) : ?>
				<button name="save" class="button-primary woocommerce-save-button" type="submit" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
			<?php endif; ?>
			<?php wp_nonce_field( 'woocommerce-dintero-hp-settings' ); ?>
		</p>
	</form>
</div>
