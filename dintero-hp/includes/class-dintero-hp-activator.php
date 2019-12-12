<?php
/**
 * Fired during plugin activation
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    dintero-hp
 * @subpackage dintero-hp/includes
 */
class Dintero_HP_Activator
{

    /**
     * Activate Plugin.
     */
    public static function activate()
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }
        $default = [
            //options should be here
        ];
        update_option('dintero_hp_option', $default);
    }
}
