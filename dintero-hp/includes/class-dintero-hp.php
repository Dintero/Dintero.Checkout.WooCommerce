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
class Dintero_HP {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @access protected
	 * @var    Dintero_HP_Loader $loader Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @access protected
	 * @var    string $dintero_hp The string used to uniquely identify this plugin.
	 */
	protected $dintero_hp;

	/**
	 * The plugin basename of this plugin.
	 *
	 * @access protected
	 * @var    string $plugin_basename The string used as the plugin basename.
	 */
	protected $plugin_basename;

	/**
	 * The current version of the plugin.
	 *
	 * @access protected
	 * @var    string $version The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @param string $plugin_basename The basename of this plugin.
	 */
	public function __construct( $plugin_basename ) {
		$this->plugin_basename = $plugin_basename;
		if ( defined( 'DINTERO_HP_VERSION' ) ) {
			$this->version = DINTERO_HP_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->dintero_hp = 'dintero-hp';

		$this->load_dependencies();
		$this->define_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Practitioners_Acceptor_Loader. Orchestrates the hooks of the plugin.
	 * - Practitioners_Acceptor_Admin. Defines all hooks for the admin area.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @access private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-dintero-hp-loader.php';

		$this->loader = new Dintero_HP_Loader();
	}

	/**
	 * Register all of the hooks related to plugin functionality.
	 *
	 * @access private
	 */
	private function define_hooks() {
		$this->loader->add_action( 'woocommerce_after_register_post_type', $this, 'init_gateway_class' );
		$this->loader->add_filter( 'woocommerce_payment_gateways', $this, 'add_payment_gateway_class' );
		// Redirect to order cancelled page if response has an error
		$this->loader->add_action( 'template_redirect', $this, 'check_response', 1 );
	}


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Load the payment gateway class after post types are registered.
	 */
	public function init_gateway_class() {
		/**
		 * The custom payment gateway class.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-gateway-dintero-hp.php';
	}

	/**
	 * Add the custom payment gateway to WooCommerce.
	 *
	 * @return array     WooCommerce payment methods
	 */
	public function add_payment_gateway_class( $methods ) {
		$methods[] = 'WC_Gateway_Dintero_HP';

		return $methods;
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @return string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->dintero_hp;
	}

	/**
	 * The basename of the plugin.
	 *
	 * @return string    The basename of the plugin.
	 */
	public function get_plugin_basename() {
		return $this->plugin_basename;
	}


	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Check response on order received page.
	 */
	public function check_response() {
		global $wp;
		if ( is_checkout() and is_wc_endpoint_url( 'order-received' ) ) {
			// Get the order ID
			$order_id = absint( $wp->query_vars['order-received'] );
			$order    = wc_get_order( $order_id );

			if ( ! empty( $order ) and $order instanceof WC_Order ) {
				if ( $order->get_payment_method() === 'dintero-hp' ) {
					if ( empty( $_GET['transaction_id'] ) ) {
						$order_cancelled_url = $order->get_cancel_order_url_raw();
						wp_redirect( $order_cancelled_url );
						exit;
					} else {
						WC()->cart->empty_cart();
					}
				}
			}
		}
	}
}
