<?php
/**
 * Dintero WooCommerce Extension Setting Handlers.
 *
 * @class   WC_Dintero_CH_Setting
 * @package Dintero/Classes
 */

defined( 'ABSPATH' ) || exit;

require_once WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-settings-api.php';

class WC_Dintero_CH_Setting extends WC_Settings_API {

	/**
	 * The single instance of the class.
	 *
	 * @var WC_Dintero_CH_Setting|null
	 */
	protected static $instance = null;	

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id = 'dintero-checkout';
	}

	/**
	 * Gets the main WC_Dintero_CH_Setting Instance.
	 *
	 * @since 2.1
	 * @static
	 * @return WC_Dintero_CH_Setting Main instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woocommerce' ), '2.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '2.1' );
	}

	public function get( $key ) {
		return $this->get_option( $key );
	}

	public function getDefault( $key ) {
		return $this->get_field_default( $key );
	}
}
