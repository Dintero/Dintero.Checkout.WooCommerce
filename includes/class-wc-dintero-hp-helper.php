<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */
class Dintero_HP_Helper
{
	/**
	 * @var $instance
	 */
	protected static $instance;

	/**
	 * Dintero_HP_Helper constructor.
	 */
	private function __construct()
	{

	}

	/**
	 * Cloning object disabled
	 */
	private function __clone()
	{

	}

	/**
	 * @return Dintero_HP_Helper
	 */
	public static function instance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Retrieving shipping tax rate
	 *
	 * @return float
	 */
	public function get_shipping_tax_rate() {

		if ( WC()->cart->shipping_tax_total <= 0) {
			return 0.00;
		}

		$shipping_tax_rate = 0;
		$shipping_rates = WC_Tax::get_shipping_tax_rates();
		$vat = array_shift( $shipping_rates );

		if ( isset( $vat['rate'] ) ) {
			return round( $vat['rate'] ,2 );
		}

		return round( $shipping_tax_rate,2 );
	}

	public function convert_to_dintero_metadata($meta_data) {
		if (null == $meta_data) {
			return null;
		}
		if (count($meta_data) === 0) {
			return null;
		}
		if (!$this->is_associative_array($meta_data)) {
			return null;
		}
		return $meta_data;
	}

	private function is_associative_array(array $arr) {
		if (array() === $arr) {
			return false;
		}
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
}
