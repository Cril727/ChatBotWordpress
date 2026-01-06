<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://cpro7.wordpress.com
 * @since             1.0.1
 * @package           Chat_Bot
 *
 * @wordpress-plugin
 * Plugin Name:       Chat Bot
 * Plugin URI:        https://github.com/Cril727/ChatBotWordpress
 * Description:       Este es un plugin para conectar el contendido de la pagina con un chat bot interactivo
 * Version:           1.0.1
 * Author:            Cristian Garcia
 * Author URI:        https://cpro7.wordpress.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       chat-bot
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.1 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CHAT_BOT_VERSION', '1.0.1' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-chat-bot-activator.php
 */
function activate_chat_bot() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-chat-bot-activator.php';
	Chat_Bot_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-chat-bot-deactivator.php
 */
function deactivate_chat_bot() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-chat-bot-deactivator.php';
	Chat_Bot_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_chat_bot' );
register_deactivation_hook( __FILE__, 'deactivate_chat_bot' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-chat-bot.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.1
 */
function run_chat_bot() {

	$plugin = new Chat_Bot();
	$plugin->run();

}
run_chat_bot();
