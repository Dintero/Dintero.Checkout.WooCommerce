<?php
/**
 * WooCommerce Dintero HP Settings Checkout
 *
 * @package WooCommerce/Admin
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Dintero_HP_Settings_Checkout', false ) ) {
	return new WC_Dintero_HP_Settings_Checkout();
}

/**
 * WC_Admin_Settings_General.
 */
class WC_Dintero_HP_Settings_Checkout extends WC_Dintero_HP_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'dintero-hp';
		$this->label = __( 'Dintero Checkout', 'woocommerce' );

		//add_action( 'woocommerce_admin_field_payment_gateways', array( $this, 'payment_gateways_setting' ) );

		$this->init_form_fields();
		$this->init_settings();

		parent::__construct();		
	}

	public function init_form_fields() {
		$this->form_fields = array(
			/*
			'enabled'                          => array(
				'title'       => __( 'Enable/Disable' ),
				'label'       => __( 'Enable Dintero Hosted Page Gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title'                            => array(
				'title'       => __( 'Title' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.' ),
				'default'     => __( 'Dintero' ),
				'desc_tip'    => true,
			),
			'description'                      => array(
				'title'       => __( 'Description' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.' ),
				'default'     => __( 'Pay through Dintero gateway.' ),
				'desc_tip'    => true,
			),*/
			'account_id'                       => array(
				'title'       => __( 'Account ID:' ),
				'type'        => 'text',
				'description' => __( 'Found under (SETTINGS >> Account) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_test_credentials'          => array(
				'title'       => __( 'Client Test:' ),
				'type'        => 'title',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' )
			),
			'test_client_id'                   => array(
				'title'       => __( 'Test Client ID:' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_client_secret'               => array(
				'title'       => __( 'Test Client Secret:' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'test_profile_id'                  => array(
				'title'       => __( 'Test Payment Profile ID:' ),
				'type'        => 'text',
				'description' => __( 'Test payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'client_production_credentials'    => array(
				'title'       => __( 'Client Production:' ),
				'type'        => 'title',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
			),
			'production_client_id'             => array(
				'title'       => __( 'Production Client ID:' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'production_client_secret'         => array(
				'title'       => __( 'Production Client Secret:' ),
				'type'        => 'text',
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'production_profile_id'            => array(
				'title'       => __( 'Production Payment Profile ID:' ),
				'type'        => 'text',
				'description' => __( 'Production payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'checkout_settings'                => array(
				'title'       => __( 'Checkout:' ),
				'type'        => 'title',
				'description' => __( 'Checkout settings.' )
			),
			'test_mode'                        => array(
				'title'       => __( 'Test mode:' ),
				'label'       => __( 'Enable Test Mode' ),
				'type'        => 'checkbox',
				'description' => __( 'Put the payment gateway in test mode using client test credentials.' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'callback_verification'            => array(
				'title'       => __( 'Callback URL Verification:' ),
				'label'       => __( 'Enable Callback URL Server-to-Server Verification' ),
				'type'        => 'checkbox',
				'description' => __( 'Enabling this will send callback URL to the API and verify the transaction when a callback request received. Disabling this will verify the transaction using parameters returned to the return page.' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			/*
			'checkout_logo_width'              => array(
				'title'       => __( 'Dintero Checkout Logo Width (in pixels)' ),
				'type'        => 'number',
				'description' => __( 'The width of Dintero\'s logo on the checkout page in pixels.' ),
				'default'     => 600,
				'desc_tip'    => true,
			),
			'capture_settings'                 => array(
				'title'       => __( 'Payment Capture' ),
				'type'        => 'title',
				'description' => __( 'Payment Capture settings.' )
			),
			'default_order_status' => array(
				'title'       => __( 'Default Order Status' ),
				'type'        => 'select',
				'options'     => array(
					'wc-processing' => _x( 'Processing', 'Order status' ),
					'wc-on-hold'    => _x( 'On hold', 'Order status' ),
				),
				'default'     => 'wc-processing',
				'description' => __( 'When payment Authorized.' ),
				'desc_tip'    => true
			),*/
			'manual_capture_settings' => array(
				'title'       => __( 'Capture order when:' ),
				'type'        => 'title',
			),
			'manual_capture_status'            => array(
				'title'       => __( 'Order status is changed to: ' ),
				'type'        => 'select',
				'options'     => wc_get_order_statuses(),
				'default'     => 'wc-completed',
				'description' => __( 'Select a status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true
			),
			/*
			'additional_manual_capture_status' => array(
				'title'       => __( 'Order status is changed to (additional): ' ),
				'type'        => 'select',
				'options'     => ( array(
					                   - 1 => '--- Disable Additional Manual Capture Order Status ---'
				                   ) + wc_get_order_statuses() ),
				'default'     => -1,
				'description' => __( 'Select an additional status which the payment will be manually captured if the order status changed to it.' ),
				'desc_tip'    => true
			),
			'cancel_refund_settings'           => array(
				'title'       => __( 'Cancel or refund order when:' ),
				'type'        => 'title'
			),
			'additional_cancel_status'         => array(
				'title'       => __( 'Order status is changed to:' ),
				'type'        => 'select',
				'options'     => ( array(
					                   - 1 => '--- Disable Additional Cancellation Order Status ---'
				                   ) + wc_get_order_statuses() ),
				'default'     => - 1,
				'description' => __( 'Select an additional status that will be used to cancel the order. Status "Cancelled" will be always used to cancel the order.' ),
				'desc_tip'    => true
			),
			'additional_refund_status'         => array(
				'title'       => __( 'Order status is changed to (additional): ' ),
				'type'        => 'select',
				'options'     => ( array(
					                   - 1 => '--- Disable Additional Refund Order Status ---'
				                   ) + wc_get_order_statuses() ),
				'default'     => - 1,
				'description' => __( 'Select an additional status that will be used to refund the order payment. Status "Refunded" will be always used to refund the order payment.' ),
				'desc_tip'    => true
			),*/
			'embed_settings'                => array(
				'title'       => __( 'Embedding Dintero Checkout:' ),
				'type'        => 'title',
				'description' => __( '' )
			),
			'embed_enable'                        => array(
				'title'       => __( 'Enable:' ),
				'label'       => __( 'Enable Embed Checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Embed Checkout on Checkout page' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'express_settings'                => array(
				'title'       => __( 'Checkout Express' ),
				'type'        => 'title',
				'description' => __( '' )
			),
			'express_enable'                        => array(
				'title'       => __( 'Enable:' ),
				'label'       => __( 'Enable Checkout Express' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Checkout Express on Checkout page' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'express_rewards'                        => array(
				'title'       => __( 'Enable Rewards:' ),
				'label'       => __( 'Enable Checkout Express Rewards' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable or disable Dintero Checkout Express Rewards' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'express_shopping_price'                        => array(
				'title'       => __( 'Show Shopping Price:' ),
				'label'       => __( 'Show/Hide Shipping Price' ),
				'type'        => 'checkbox',
				'description' => __( 'Show or hide shopping price' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'branding_title'                => array(
				'title'       => __( 'Branding:' ),
				'type'        => 'title',
				'description' => __( '' )
			),
			'branding_subtitle_footer'                => array(
				'title'       => __( 'Footer:' ),
				'type'        => 'subtitle',
				'description' => __( '' )
			),
			'branding_footer_url'            => array(
				'title'       => __( 'URL:' ),
				'type'        => 'text',
				'description' => __( 'You can change color & size in Dintero Backoffice. Paste the new URL here:<br />Preview:<div>'.$this->get_icon_footer()."</div>"),
				'default'     => '',
				'desc_tip'    => false,
			),
			'branding_subtitle_checkout'                => array(
				'title'       => __( 'In Checkout:' ),
				'type'        => 'subtitle',
				'description' => __( '' )
			),
			'branding_checkout_url'            => array(
				'title'       => __( 'URL:' ),
				'type'        => 'text',
				'description' => __( 'You can change color & size in Dintero Backoffice. Paste the new URL here:<br />Preview:<div>'.$this->get_icon_checkout()."</div>" ),
				'default'     => '',
				'desc_tip'    => false,
			),
		);
	}

	/**
	 * Get settings array.
	 *
	 * @return array
	 */
	public function get_settings() {

		$currency_code_options = get_woocommerce_currencies();

		foreach ( $currency_code_options as $code => $name ) {
			$currency_code_options[ $code ] = $name . ' (' . get_woocommerce_currency_symbol( $code ) . ')';
		}

		$woocommerce_default_customer_address_options = array(
			''                 => __( 'No location by default', 'woocommerce' ),
			'base'             => __( 'Shop base address', 'woocommerce' ),
			'geolocation'      => __( 'Geolocate', 'woocommerce' ),
			'geolocation_ajax' => __( 'Geolocate (with page caching support)', 'woocommerce' ),
		);

		if ( version_compare( PHP_VERSION, '5.4', '<' ) ) {
			unset( $woocommerce_default_customer_address_options['geolocation'], $woocommerce_default_customer_address_options['geolocation_ajax'] );
		}

		$settings = array();

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
	}

	/**
	 * Output a color picker input box.
	 *
	 * @param mixed  $name Name of input.
	 * @param string $id ID of input.
	 * @param mixed  $value Value of input.
	 * @param string $desc (default: '') Description for input.
	 */
	public function color_picker( $name, $id, $value, $desc = '' ) {
		echo '<div class="color_box">' . wc_help_tip( $desc ) . '
			<input name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" type="text" value="' . esc_attr( $value ) . '" class="colorpick" /> <div id="colorPickerDiv_' . esc_attr( $id ) . '" class="colorpickdiv"></div>
		</div>';
	}

	/**
	 * Output the settings.
	 */
	public function output() {		
		$this->admin_options();

		$settings = $this->get_settings();
		WC_Dintero_HP_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings.
	 */
	public function save() {
		$settings = $this->get_settings();

		WC_Dintero_HP_Admin_Settings::save_fields( $settings );
	}

	private function get_icon_footer(){
		return WCDHP()->checkout()->get_icon_footer(420);
	}

	private function get_icon_checkout(){
		return WCDHP()->checkout()->get_icon_checkout(420);
	}
}

return new WC_Dintero_HP_Settings_Checkout();
