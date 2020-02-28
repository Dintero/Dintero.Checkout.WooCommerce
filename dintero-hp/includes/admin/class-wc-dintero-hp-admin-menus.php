<?php
/**
 * Setup menus in WP admin.
 *
 * @package WooCommerce\Admin
 * @version 2.5.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Dintero_HP_Admin_Menus', false ) ) {
	return new WC_Dintero_HP_Admin_Menus();
}

/**
 * WC_Admin_Menus Class.
 */
class WC_Dintero_HP_Admin_Menus {

	/**
	 * Hook in tabs.
	 */
	public function __construct() {
		// Add menus.
		add_action( 'admin_menu', array( $this, 'settings_menu' ), 150 );

		// Handle saving settings earlier than load-{page} hook to avoid race conditions in conditional menus.
		add_action( 'wp_loaded', array( $this, 'save_settings' ) );
	}

	/**
	 * Add menu item.
	 */
	public function settings_menu() {
		$settings_page = add_submenu_page( 'woocommerce', __( 'Dintero Checkout Settings', 'woocommerce' ), __( 'Dintero Checkout Settings', 'woocommerce' ), 'manage_woocommerce', 'wc-dintero-settings', array( $this, 'settings_page' ) );

		add_action( 'load-' . $settings_page, array( $this, 'settings_page_init' ) );
	}

	/**
	 * Loads gateways and shipping methods into memory for use within settings.
	 */
	public function settings_page_init() {
		//WC()->payment_gateways();
		//WC()->shipping();

		// Include settings pages.
		WC_Dintero_HP_Admin_Settings::get_settings_pages();

		$nonce = wp_create_nonce( 'dhp-nonce' );
			echo( '<input type="hidden" id="_dhp_setting_nonce" name="_dhp_setting_nonce" value="1a9c366a6c" />' );

		// Add any posted messages.
		if ( ! empty( $_GET['wc_error'] ) ) { // WPCS: input var okay, CSRF ok.
			WC_Dintero_HP_Admin_Settings::add_error( wp_kses_post( wp_unslash( $_GET['wc_error'] ) ) ); // WPCS: input var okay, CSRF ok.
		}

		if ( ! empty( $_GET['wc_message'] ) ) { // WPCS: input var okay, CSRF ok.
			WC_Dintero_HP_Admin_Settings::add_message( wp_kses_post( wp_unslash( $_GET['wc_message'] ) ) ); // WPCS: input var okay, CSRF ok.
		}

		//do_action( 'woocommerce_dhp_settings_page_init' );
	}

	/**
	 * Handle saving of settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		try {
			if ( isset( $_REQUEST['_dhp_setting_nonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_dhp_setting_nonce'] ) );
				if ( ! wp_verify_nonce( $nonce, 'dhp-nonce' ) ) {
					echo( 'We were unable to process your request' );
				} else {
					global $current_tab, $current_section;

					// We should only save on the settings page.
					if ( ! is_admin() || ! isset( $_GET['page'] ) || 'wc-dintero-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
						return;
					}

					// Include settings pages.
					WC_Dintero_HP_Admin_Settings::get_settings_pages();

					// Get current tab/section.
					$current_tab     = empty( $_GET['tab'] ) ? 'dintero-hp' : sanitize_title( wp_unslash( $_GET['tab'] ) ); // WPCS: input var okay, CSRF ok.
					$current_section = empty( $_REQUEST['section'] ) ? '' : sanitize_title( wp_unslash( $_REQUEST['section'] ) ); // WPCS: input var okay, CSRF ok.

					// Save settings if data has been posted.
					if ( '' !== $current_section && apply_filters( "woocommerce_save_settings_{$current_tab}_{$current_section}", ! empty( $_POST['save'] ) ) ) { // WPCS: input var okay, CSRF ok.
						WC_Dintero_HP_Admin_Settings::save();
					} elseif ( '' === $current_section && apply_filters( "woocommerce_save_settings_{$current_tab}", ! empty( $_POST['save'] ) ) ) { // WPCS: input var okay, CSRF ok.
						WC_Dintero_HP_Admin_Settings::save();
					}
				}
			}
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Init the settings page.
	 */
	public function settings_page() {
		WC_Dintero_HP_Admin_Settings::output();
	}
}

return new WC_Dintero_HP_Admin_Menus();
