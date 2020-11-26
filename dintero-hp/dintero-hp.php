<?php
/**
Plugin Name: Dintero Checkout
Description: Dintero Checkout - Express Checkout
Version:     2020.11.25
Author:      Dintero
Author URI:  mailto:integration@dintero.com
Text Domain: dintero-hp
Domain Path: /languages
 *
 * @package /dintero-hp
 */

defined( 'ABSPATH' ) || exit;

define( 'DINTERO_HP_VERSION', '2020.11.25' );

if ( ! defined( 'DHP_PLUGIN_FILE' ) ) {
	define( 'DHP_PLUGIN_FILE', __FILE__ );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-dintero-hp-activator.php
 */
function activate_dintero_hp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dintero-hp-activator.php';
	Dintero_HP_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-dintero-hp-deactivator.php
 */
function deactivate_dintero_hp() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-dintero-hp-deactivator.php';
	Dintero_HP_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_dintero_hp' );
register_deactivation_hook( __FILE__, 'deactivate_dintero_hp' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-dintero-hp.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-wc-dintero-hp.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */

function run_dintero_hp() {
	$plugin_basename = plugin_basename( __FILE__ );
	$plugin = new Dintero_HP($plugin_basename);
	$plugin->run();
}
run_dintero_hp();

/**
 * Get instance of WooCommerce Dintero Plugin
 */
function WCDHP() {
	return WC_Dintero_HP::instance();
}

// Global for backwards compatibility.
$GLOBALS['woocommerce-dintero'] = WCDHP();
