<?php
/**
 * Fired during plugin deactivation
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's
 * deactivation.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */
class Dintero_HP_Deactivator {

    /**
     * Deactivate plugin.
     */
    public static function deactivate() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        // Unregister plugin settings on deactivation.
        unregister_setting(
            'dintero_hp',
            'dintero_hp_option'
        );
    }

}
