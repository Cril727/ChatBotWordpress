<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://cpro7.wordpress.com
 * @since      1.0.2
 *
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.2
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 * @author     Cristian Garcia <criatiangarcia637@gmail.com>
 */
class Chat_Bot_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.2
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook('chatbot_full_reindex');
	}

}
