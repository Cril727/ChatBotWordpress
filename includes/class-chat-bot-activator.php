<?php

/**
 * Fired during plugin activation
 *
 * @link       https://cpro7.wordpress.com
 * @since      1.0.1
 *
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.1
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 * @author     Cristian Garcia <criatiangarcia637@gmail.com>
 */
class Chat_Bot_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.1
	 */
	public static function activate() {
		self::create_embeddings_table();
	}

	private static function create_embeddings_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'chatbot_embeddings';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			source_type varchar(50) NOT NULL,
			source_id bigint(20) NOT NULL,
			chunk_text longtext NOT NULL,
			embedding longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_source (source_type, source_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

}
