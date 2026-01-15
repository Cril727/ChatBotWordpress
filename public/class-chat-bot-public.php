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
	private const RATE_LIMIT_PER_MINUTE = 5;
	private const RATE_LIMIT_BURST = 3;
	private const RATE_LIMIT_BURST_WINDOW = 10;
	private const RATE_LIMIT_MESSAGE_MAX = 1000;

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

		$button_icon = get_option( 'chatbot_button_icon', false );
		if ( $button_icon === false ) {
			$button_icon = plugin_dir_url( __FILE__ ) . 'img/img-bot.png';
		}

		$ui_settings = array(
			'bot_name' => get_option( 'chatbot_bot_name', 'Chatbot' ),
			'button_label' => get_option( 'chatbot_button_label', '💬' ),
			'button_icon' => $button_icon,
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

		$raw_message = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';
		$message = sanitize_text_field($raw_message);
		$current_post_id = intval($_POST['post_id'] ?? 0);
		$current_url = sanitize_text_field($_POST['current_url'] ?? '');
		$session_id = sanitize_text_field($_POST['session_id'] ?? '');

		if ($this->message_too_long($raw_message)) {
			wp_send_json_error('El mensaje excede los 1000 caracteres permitidos.');
		}

		if (empty($message)) {
			wp_send_json_error('Mensaje vacío');
		}

		if (!$this->check_rate_limit($session_id)) {
			wp_send_json_error('Demasiadas solicitudes. Intenta de nuevo en unos segundos.');
		}

		$chat = new Chat_Bot_Chat();
		$response = $chat->process_message($message, $current_post_id, $current_url, $session_id);

		wp_send_json_success(['response' => $response]);
	}

	private function message_too_long($message) {
		$message = trim((string) $message);
		if ($message === '') {
			return false;
		}

		if (function_exists('mb_strlen')) {
			return mb_strlen($message) > self::RATE_LIMIT_MESSAGE_MAX;
		}

		return strlen($message) > self::RATE_LIMIT_MESSAGE_MAX;
	}

	private function check_rate_limit($session_id) {
		if (is_user_logged_in()) {
			return true;
		}

		$identifier = $this->get_rate_limit_identifier($session_id);
		if ($identifier === '') {
			return true;
		}

		$key = 'chatbot_rl_' . md5($identifier);

		if (!$this->check_rate_limit_bucket($key . '_m', self::RATE_LIMIT_PER_MINUTE, MINUTE_IN_SECONDS)) {
			return false;
		}

		if (!$this->check_rate_limit_bucket($key . '_b', self::RATE_LIMIT_BURST, self::RATE_LIMIT_BURST_WINDOW)) {
			return false;
		}

		return true;
	}

	private function check_rate_limit_bucket($key, $limit, $ttl) {
		$count = (int) get_transient($key);
		if ($count >= $limit) {
			return false;
		}

		$count++;
		set_transient($key, $count, $ttl);
		return true;
	}

	private function get_rate_limit_identifier($session_id) {
		if (!empty($session_id)) {
			return $session_id;
		}

		$ip = '';
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim($forwarded[0]);
		} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field($ip);
	}

}
