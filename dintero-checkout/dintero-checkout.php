<?php
/**
Plugin Name: Dintero Checkout Express
Description: Express Checkout by Dintero
Version:     2019.12.04
Author:      Dintero
Author URI:  mailto:integration@dintero.com
Text Domain: dintero-checkout
Domain Path: /languages
 *
 * @package /dintero-checkout
 */

defined( 'ABSPATH' ) || exit;

define( 'DINTERO_CH_VERSION', '2020.02.26' );

if ( ! defined( 'DCH_PLUGIN_FILE' ) ) {
	define( 'DCH_PLUGIN_FILE', __FILE__ );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-dintero-checkout-activator.php
 */
function activate_Dintero_CH() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dintero-ch-activator.php';
	Dintero_CH_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-dintero-checkout-deactivator.php
 */
function deactivate_Dintero_CH() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dintero-ch-deactivator.php';
	Dintero_CH_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_Dintero_CH' );
register_deactivation_hook( __FILE__, 'deactivate_Dintero_CH' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-dintero-ch.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wc-dintero-ch.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */

function run_Dintero_CH() {
	$plugin_basename = plugin_basename( __FILE__ );
	$plugin = new Dintero_CH($plugin_basename);
	$plugin->run();
}
run_Dintero_CH();

/**
 * Get instance of WooCommerce Dintero Plugin
 */
function WCDCH() {
	return WC_Dintero_CH::instance();
}

// Global for backwards compatibility.
$GLOBALS['woocommerce-dintero-ch'] = WCDCH();
