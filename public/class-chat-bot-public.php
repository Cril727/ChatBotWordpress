<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://cpro7.wordpress.com
 * @since      1.0.2
 *
 * @package    Chat_Bot
 * @subpackage Chat_Bot/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Chat_Bot
 * @subpackage Chat_Bot/public
 * @author     Cristian Garcia <criatiangarcia637@gmail.com>
 */
class Chat_Bot_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.2
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.2
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Chat_Bot_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Chat_Bot_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/chat-bot-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.2
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Chat_Bot_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Chat_Bot_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$ui_settings = array(
			'bot_name' => get_option( 'chatbot_bot_name', 'Chatbot' ),
			'button_label' => get_option( 'chatbot_button_label', '💬' ),
			'position' => get_option( 'chatbot_position', 'bottom-right' ),
			'theme' => get_option( 'chatbot_theme', 'light' ),
			'primary_color' => get_option( 'chatbot_primary_color', '#10b981' ),
			'accent_color' => get_option( 'chatbot_accent_color', '#3b82f6' ),
			'widget_width' => get_option( 'chatbot_widget_width', '' ),
			'widget_height' => get_option( 'chatbot_widget_height', '' ),
			'font_family' => get_option( 'chatbot_font_family', '' ),
		);

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/chat-bot-public.js', array( 'jquery' ), $this->version, false );

		wp_localize_script( $this->plugin_name, 'chatbot_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'chatbot_nonce' ),
			'ui_settings' => $ui_settings,
		) );

		wp_add_inline_script(
			$this->plugin_name,
			'window.chatbot_ui_settings = ' . wp_json_encode( $ui_settings ) . ';',
			'before'
		);

		// Pass current post ID
		if ( is_singular() ) {
			wp_localize_script( $this->plugin_name, 'chatbot_post_id', array( 'id' => get_the_ID() ) );
		}

		// Pass current URL
		wp_localize_script( $this->plugin_name, 'chatbot_current_url', array( 'url' => home_url( add_query_arg( null, null ) ) ) );

	}

	/**
	 * Handle AJAX chat message
	 */
	public function handle_chat_message() {
		check_ajax_referer('chatbot_nonce', 'nonce');

		$message = sanitize_text_field($_POST['message'] ?? '');
		$current_post_id = intval($_POST['post_id'] ?? 0);
		$current_url = sanitize_text_field($_POST['current_url'] ?? '');

		if (empty($message)) {
			wp_send_json_error('Mensaje vacío');
		}

		$chat = new Chat_Bot_Chat();
		$response = $chat->process_message($message, $current_post_id, $current_url);

		wp_send_json_success(['response' => $response]);
	}

}
