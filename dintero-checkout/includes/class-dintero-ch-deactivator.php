<?php
/**
 * Fired during plugin deactivation
 *
 * @package    dintero-checkout
 * @subpackage dintero-checkout/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's
 * deactivation.
 *
 * @package    dintero-checkout
 * @subpackage dintero-checkout/includes
 */
class Dintero_CH_Deactivator {

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Unregister plugin settings on deactivation.
		unregister_setting(
			'dintero_ch',
			'dintero_ch_option'
		);
	}

}
