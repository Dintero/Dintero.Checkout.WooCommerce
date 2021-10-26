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
		$metadata = array();
		foreach ( $meta_data as $key => $value ) {
			$metadata[$key] = json_encode($value);
		}
		return $metadata;
	}

	public function convert_to_dintero_discounts($applied_coupons) {
		if (is_null($applied_coupons)) {
			return array();
		}
		$discount_codes = array();
		foreach ($applied_coupons as $coupon) {
			$discount_codes[] = $coupon;
		}
		return $discount_codes;
	}

	public function url_to_store_id($url) {
		$trimmed_url = str_replace( array( 'http://', 'https://', 'www.'), array( '', '', '' ), $url);
		$trimmed_url = rtrim($trimmed_url, '/');
		return $trimmed_url;
	}

	public function to_dintero_amount($amount, $round_precision = 2) {
		return intval(round($amount * 100, $round_precision));
	}

	private function is_associative_array(array $arr) {
		if (array() === $arr) {
			return false;
		}
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/**
	 *
	 */
	public function get_coupon_lines($order_total)
	{
		$dintero_items = [];
		// PW Gift Cards.
		if (!empty(WC()->session->get('pw-gift-card-data'))) {
			$pw_gift_cards = WC()->session->get('pw-gift-card-data');
			foreach ($pw_gift_cards['gift_cards'] as $code => $value) {
				$coupon_amount       = $value * 100 * -1;
				if (abs($coupon_amount) > $order_total) {
					$coupon_amount = $order_total * -1;
				}
				$label               = esc_html__('Gift card', 'pw-woocommerce-gift-cards') . ' ' . $code;
				$id = 'pw_gift_cards_'. $code;
				$gift_card           = array(
					'id' => $id,
					'line_id' => $id,
					'description'           => $label,
					'quantity'              => 1,
					'amount'            => $coupon_amount,
					'vat'              => 0,
					'vat_amount'              => 0,
				);
				$dintero_items[] = $gift_card;
			}
		}
		return $dintero_items;
	}
}
