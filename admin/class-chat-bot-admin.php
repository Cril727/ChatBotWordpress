<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://cpro7.wordpress.com
 * @since      1.0.1
 *
 * @package    Chat_Bot
 * @subpackage Chat_Bot/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Chat_Bot
 * @subpackage Chat_Bot/admin
 * @author     Cristian Garcia <criatiangarcia637@gmail.com>
 */
class Chat_Bot_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.1
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.1
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/chat-bot-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.1
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/chat-bot-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			'Chat Bot Settings',
			'Chat Bot',
			'manage_options',
			'chat-bot-settings',
			array( $this, 'settings_page' ),
			'dashicons-format-chat',
			30
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'chatbot_settings', 'chatbot_provider' );
		register_setting( 'chatbot_settings', 'chatbot_openai_api_key' );
		register_setting( 'chatbot_settings', 'chatbot_openai_model' );
		register_setting( 'chatbot_settings', 'chatbot_google_api_key' );
		register_setting( 'chatbot_settings', 'chatbot_google_model' );
		register_setting( 'chatbot_settings', 'chatbot_custom_queries', array( $this, 'sanitize_custom_queries' ) );

		add_settings_section(
			'chatbot_main_section',
			'Configuración del Proveedor de IA',
			null,
			'chatbot_settings'
		);

		add_settings_field(
			'provider',
			'Proveedor de IA',
			array( $this, 'provider_field_callback' ),
			'chatbot_settings',
			'chatbot_main_section'
		);

		add_settings_field(
			'openai_api_key',
			'Clave API de OpenAI',
			array( $this, 'api_key_field_callback' ),
			'chatbot_settings',
			'chatbot_main_section'
		);

		add_settings_field(
			'openai_model',
			'Modelo de OpenAI',
			array( $this, 'model_field_callback' ),
			'chatbot_settings',
			'chatbot_main_section'
		);

		add_settings_field(
			'google_api_key',
			'Clave API de Google AI',
			array( $this, 'google_api_key_field_callback' ),
			'chatbot_settings',
			'chatbot_main_section'
		);

		add_settings_field(
			'google_model',
			'Modelo de Google AI',
			array( $this, 'google_model_field_callback' ),
			'chatbot_settings',
			'chatbot_main_section'
		);


		add_settings_section(
			'chatbot_queries_section',
			'Consultas Personalizadas',
			array( $this, 'queries_section_callback' ),
			'chatbot_settings'
		);

		add_settings_field(
			'custom_queries',
			'Consultas SQL (una por línea)',
			array( $this, 'custom_queries_field_callback' ),
			'chatbot_settings',
			'chatbot_queries_section'
		);
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1>Configuración del Chat Bot</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'chatbot_settings' );
				do_settings_sections( 'chatbot_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Provider field
	 */
	public function provider_field_callback() {
		$value = get_option( 'chatbot_provider', 'google' );
		echo '<select name="chatbot_provider">';
		echo '<option value="openai" ' . selected( $value, 'openai', false ) . '>OpenAI</option>';
		echo '<option value="google" ' . selected( $value, 'google', false ) . '>Google AI (Gemini)</option>';
		echo '</select>';
		echo '<p class="description">Selecciona el proveedor de IA a usar (por defecto: Google AI).</p>';
	}

	/**
	 * API key field
	 */
	public function api_key_field_callback() {
		$value = get_option( 'chatbot_openai_api_key' );
		echo '<input type="password" name="chatbot_openai_api_key" value="' . esc_attr( $value ) . '" size="50" />';
		echo '<p class="description">Ingresa tu clave API de OpenAI.</p>';
	}

	/**
	 * Google API key field
	 */
	public function google_api_key_field_callback() {
		$value = get_option( 'chatbot_google_api_key' );
		echo '<input type="password" name="chatbot_google_api_key" value="' . esc_attr( $value ) . '" size="50" />';
		echo '<p class="description">Ingresa tu clave API de Google AI para usar Gemini.</p>';
	}

	/**
	 * Google model field
	 */
	public function google_model_field_callback() {
		$value = get_option( 'chatbot_google_model', 'gemini-2.5-flash' );
		echo '<select name="chatbot_google_model">';
		echo '<option value="gemini-pro" ' . selected( $value, 'gemini-pro', false ) . '>Gemini Pro</option>';
		echo '<option value="gemini-1.5-flash" ' . selected( $value, 'gemini-1.5-flash', false ) . '>Gemini 1.5 Flash</option>';
		echo '<option value="gemini-2.5-flash" ' . selected( $value, 'gemini-2.5-flash', false ) . '>Gemini 2.5 Flash</option>';
		echo '</select>';
		echo '<p class="description">Selecciona el modelo de Google AI a usar.</p>';
	}

	/**
	 * Model field
	 */
	public function model_field_callback() {
		$value = get_option( 'chatbot_openai_model', 'gpt-3.5-turbo' );
		echo '<select name="chatbot_openai_model">';
		echo '<option value="gpt-3.5-turbo" ' . selected( $value, 'gpt-3.5-turbo', false ) . '>GPT-3.5 Turbo</option>';
		echo '<option value="gpt-4" ' . selected( $value, 'gpt-4', false ) . '>GPT-4</option>';
		echo '<option value="gpt-4-turbo" ' . selected( $value, 'gpt-4-turbo', false ) . '>GPT-4 Turbo</option>';
		echo '</select>';
		echo '<p class="description">Selecciona el modelo de OpenAI a usar.</p>';
	}


	/**
	 * Queries section
	 */
	public function queries_section_callback() {
		echo '<p>Define consultas SQL personalizadas que el chatbot puede ejecutar en la base de datos de WordPress. Solo SELECT permitidas por seguridad, y no se permiten * ni consultas peligrosas.</p>';
	}

	/**
	 * Custom queries field
	 */
	public function custom_queries_field_callback() {
		$value = get_option( 'chatbot_custom_queries' );
		echo '<textarea name="chatbot_custom_queries" rows="10" cols="50">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">Una consulta por línea. Ejemplo:<br>SELECT name, email FROM users WHERE active = 1</p>';
	}

	/**
	 * Sanitize custom queries
	 */
	public function sanitize_custom_queries( $input ) {
		if ( empty( $input ) ) {
			return $input;
		}

		$queries = array_filter( array_map( 'trim', explode( "\n", $input ) ) );
		$sanitized = [];

		foreach ( $queries as $query ) {
			$query = trim( $query );
			if ( ! preg_match( '/^SELECT/i', $query ) ) {
				add_settings_error( 'chatbot_custom_queries', 'invalid_query', 'Solo consultas SELECT son permitidas: ' . esc_html( $query ) );
				continue;
			}
			if ( preg_match( '/\b(SELECT\s+\*|\bUNION\b|\bINFORMATION_SCHEMA\b|\bMYSQL\b|\bPERFORMANCE_SCHEMA\b|\bSYS\b)/i', $query ) ) {
				add_settings_error( 'chatbot_custom_queries', 'dangerous_query', 'Consulta potencialmente peligrosa no permitida: ' . esc_html( $query ) );
				continue;
			}
			$sanitized[] = $query;
		}

		return implode( "\n", $sanitized );
	}

}
