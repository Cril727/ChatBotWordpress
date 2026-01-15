<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://cpro7.wordpress.com
 * @since      1.0.2
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
	private $last_extraction_error = '';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.2
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_chatbot_upload_training_file', array( $this, 'handle_training_file_upload' ) );
		add_action( 'admin_post_chatbot_delete_training_file', array( $this, 'handle_training_file_delete' ) );

	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/chat-bot-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/chat-bot-admin.js', array( 'jquery', 'wp-color-picker' ), $this->version, true );

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
		register_setting( 'chatbot_settings', 'chatbot_bot_name', array( $this, 'sanitize_bot_name' ) );
		register_setting( 'chatbot_settings', 'chatbot_button_label', array( $this, 'sanitize_button_label' ) );
		register_setting( 'chatbot_settings', 'chatbot_position', array( $this, 'sanitize_position' ) );
		register_setting( 'chatbot_settings', 'chatbot_theme', array( $this, 'sanitize_theme' ) );
		register_setting( 'chatbot_settings', 'chatbot_primary_color', array( $this, 'sanitize_color' ) );
		register_setting( 'chatbot_settings', 'chatbot_accent_color', array( $this, 'sanitize_color' ) );
		register_setting( 'chatbot_settings', 'chatbot_widget_width', array( $this, 'sanitize_dimension' ) );
		register_setting( 'chatbot_settings', 'chatbot_widget_height', array( $this, 'sanitize_dimension' ) );
		register_setting( 'chatbot_settings', 'chatbot_font_family', array( $this, 'sanitize_font_family' ) );

		add_settings_section(
			'chatbot_main_section',
			'Configuración del Proveedor de IA',
			null,
			'chatbot_settings_ai'
		);

		add_settings_field(
			'provider',
			'Proveedor de IA',
			array( $this, 'provider_field_callback' ),
			'chatbot_settings_ai',
			'chatbot_main_section'
		);

		add_settings_field(
			'openai_api_key',
			'Clave API de OpenAI',
			array( $this, 'api_key_field_callback' ),
			'chatbot_settings_ai',
			'chatbot_main_section'
		);

		add_settings_field(
			'openai_model',
			'Modelo de OpenAI',
			array( $this, 'model_field_callback' ),
			'chatbot_settings_ai',
			'chatbot_main_section'
		);

		add_settings_field(
			'google_api_key',
			'Clave API de Google AI',
			array( $this, 'google_api_key_field_callback' ),
			'chatbot_settings_ai',
			'chatbot_main_section'
		);

		add_settings_field(
			'google_model',
			'Modelo de Google AI',
			array( $this, 'google_model_field_callback' ),
			'chatbot_settings_ai',
			'chatbot_main_section'
		);

		add_settings_section(
			'chatbot_design_section',
			'Personalización del Bot',
			null,
			'chatbot_settings_design'
		);

		add_settings_field(
			'bot_name',
			'Nombre del bot',
			array( $this, 'bot_name_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'button_label',
			'Texto/emoji del botón',
			array( $this, 'button_label_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'position',
			'Posición del widget',
			array( $this, 'position_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'theme',
			'Tema por defecto',
			array( $this, 'theme_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'primary_color',
			'Color primario',
			array( $this, 'primary_color_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'accent_color',
			'Color secundario',
			array( $this, 'accent_color_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'widget_width',
			'Ancho del widget (px)',
			array( $this, 'widget_width_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'widget_height',
			'Alto del widget (px)',
			array( $this, 'widget_height_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);

		add_settings_field(
			'font_family',
			'Fuente del widget',
			array( $this, 'font_family_field_callback' ),
			'chatbot_settings_design',
			'chatbot_design_section'
		);
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		?>
		<div class="wrap chatbot-settings">
			<h1>Configuración del Chat Bot</h1>
			<?php $this->render_training_notice(); ?>
			<h2 class="nav-tab-wrapper">
				<a href="#chatbot-tab-ai" class="nav-tab nav-tab-active chatbot-nav-tab" data-tab="ai">Configuración de IA</a>
				<a href="#chatbot-tab-training" class="nav-tab chatbot-nav-tab" data-tab="training">Subida de archivos</a>
				<a href="#chatbot-tab-design" class="nav-tab chatbot-nav-tab" data-tab="design">Personalización</a>
			</h2>

			<div id="chatbot-tab-ai" class="chatbot-tab-panel is-active">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'chatbot_settings' );
					do_settings_sections( 'chatbot_settings_ai' );
					submit_button();
					?>
				</form>
			</div>

			<div id="chatbot-tab-training" class="chatbot-tab-panel">
				<h2>Entrenamiento con archivos</h2>
				<p>Sube archivos para indexar su contenido y usarlos en las respuestas del chatbot.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( 'chatbot_upload_training_file' ); ?>
					<input type="hidden" name="action" value="chatbot_upload_training_file">
					<input type="file" name="chatbot_training_file" accept=".txt,.md,.csv,.pdf,.docx" required>
					<p class="description">Formatos permitidos: txt, md, csv, pdf, docx. Tamaño máximo: <?php echo esc_html( size_format( $this->get_training_max_file_size() ) ); ?>.</p>
					<?php submit_button( 'Subir e indexar' ); ?>
				</form>

				<?php $this->render_training_documents_list(); ?>
			</div>

			<div id="chatbot-tab-design" class="chatbot-tab-panel">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'chatbot_settings' );
					do_settings_sections( 'chatbot_settings_design' );
					submit_button();
					?>
				</form>
			</div>
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

	public function bot_name_field_callback() {
		$value = get_option( 'chatbot_bot_name', 'Chatbot' );
		echo '<input type="text" name="chatbot_bot_name" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">Este nombre se muestra en el encabezado del chat.</p>';
	}

	public function button_label_field_callback() {
		$value = get_option( 'chatbot_button_label', '💬' );
		echo '<input type="text" name="chatbot_button_label" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">Puede ser texto corto o un emoji (por ejemplo: 💬).</p>';
	}

	public function position_field_callback() {
		$value = get_option( 'chatbot_position', 'bottom-right' );
		$options = array(
			'bottom-right' => 'Abajo derecha',
			'bottom-left' => 'Abajo izquierda',
			'top-right' => 'Arriba derecha',
			'top-left' => 'Arriba izquierda',
		);
		echo '<select name="chatbot_position">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function theme_field_callback() {
		$value = get_option( 'chatbot_theme', 'light' );
		echo '<select name="chatbot_theme">';
		echo '<option value="light" ' . selected( $value, 'light', false ) . '>Claro</option>';
		echo '<option value="dark" ' . selected( $value, 'dark', false ) . '>Oscuro</option>';
		echo '</select>';
	}

	public function primary_color_field_callback() {
		$value = get_option( 'chatbot_primary_color', '#10b981' );
		echo '<input type="text" name="chatbot_primary_color" value="' . esc_attr( $value ) . '" class="regular-text chatbot-color-field" data-default-color="#10b981" />';
		echo '<p class="description">Color primario (ej: #10b981).</p>';
	}

	public function accent_color_field_callback() {
		$value = get_option( 'chatbot_accent_color', '#3b82f6' );
		echo '<input type="text" name="chatbot_accent_color" value="' . esc_attr( $value ) . '" class="regular-text chatbot-color-field" data-default-color="#3b82f6" />';
		echo '<p class="description">Color secundario (ej: #3b82f6).</p>';
	}

	public function widget_width_field_callback() {
		$value = get_option( 'chatbot_widget_width', '' );
		echo '<input type="number" name="chatbot_widget_width" value="' . esc_attr( $value ) . '" class="small-text" min="260" max="900" />';
		echo '<p class="description">Deja vacio para usar el valor por defecto.</p>';
	}

	public function widget_height_field_callback() {
		$value = get_option( 'chatbot_widget_height', '' );
		echo '<input type="number" name="chatbot_widget_height" value="' . esc_attr( $value ) . '" class="small-text" min="320" max="1200" />';
		echo '<p class="description">Deja vacio para usar el valor por defecto.</p>';
	}

	public function font_family_field_callback() {
		$value = get_option( 'chatbot_font_family', '' );
		echo '<input type="text" name="chatbot_font_family" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">Ejemplo: \"Poppins\", sans-serif. Deja vacio para usar la fuente del sitio.</p>';
	}

	public function sanitize_bot_name( $input ) {
		$value = sanitize_text_field( $input );
		return $value !== '' ? $value : 'Chatbot';
	}

	public function sanitize_button_label( $input ) {
		$value = sanitize_text_field( $input );
		$value = trim( $value );
		if ( $value === '' ) {
			return '💬';
		}
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, 8 );
		}
		return substr( $value, 0, 8 );
	}

	public function sanitize_position( $input ) {
		$allowed = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );
		return in_array( $input, $allowed, true ) ? $input : 'bottom-right';
	}

	public function sanitize_theme( $input ) {
		return in_array( $input, array( 'light', 'dark' ), true ) ? $input : 'light';
	}

	public function sanitize_color( $input ) {
		$color = sanitize_hex_color( $input );
		return $color ? $color : '';
	}

	public function sanitize_dimension( $input ) {
		if ( $input === '' ) {
			return '';
		}
		$value = absint( $input );
		if ( $value < 200 ) {
			$value = 200;
		}
		if ( $value > 1400 ) {
			$value = 1400;
		}
		return (string) $value;
	}

	public function sanitize_font_family( $input ) {
		$value = sanitize_text_field( $input );
		if ( $value === '' ) {
			return '';
		}
		return preg_replace( '/[^a-zA-Z0-9,\\-\\s\\\'\\"]+/', '', $value );
	}

	private function render_training_notice() {
		$notice = get_transient( 'chatbot_training_notice' );
		if ( empty( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		$type = ! empty( $notice['type'] ) ? $notice['type'] : 'info';
		$message = $notice['message'];
		delete_transient( 'chatbot_training_notice' );

		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function set_training_notice( $type, $message ) {
		set_transient(
			'chatbot_training_notice',
			array(
				'type' => $type,
				'message' => $message,
			),
			60
		);
	}

	public function handle_training_file_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No autorizado.' );
		}

		check_admin_referer( 'chatbot_upload_training_file' );

		$openai_key = get_option( 'chatbot_openai_api_key' );
		$google_key = get_option( 'chatbot_google_api_key' );
		if ( empty( $openai_key ) && empty( $google_key ) ) {
			$this->set_training_notice( 'error', 'Configura una clave de OpenAI o Google AI antes de entrenar con archivos.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		if ( empty( $_FILES['chatbot_training_file']['name'] ) ) {
			$this->set_training_notice( 'error', 'No se selecciono ningun archivo.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$max_size = $this->get_training_max_file_size();
		if ( ! empty( $_FILES['chatbot_training_file']['size'] ) && $_FILES['chatbot_training_file']['size'] > $max_size ) {
			$this->set_training_notice( 'error', 'El archivo excede el tamano maximo permitido.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$_FILES['chatbot_training_file'],
			array(
				'test_form' => false,
				'mimes' => $this->get_training_allowed_mimes(),
			)
		);

		if ( isset( $upload['error'] ) ) {
			$this->set_training_notice( 'error', 'Error al subir el archivo: ' . $upload['error'] );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$attachment_id = $this->create_training_attachment( $upload );
		if ( ! $attachment_id ) {
			$this->set_training_notice( 'error', 'No se pudo registrar el archivo en la biblioteca.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$hash = hash_file( 'sha256', $upload['file'] );
		$existing = $this->find_existing_training_file( $hash );
		if ( $existing && (int) $existing !== (int) $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
			$this->set_training_notice( 'error', 'Este archivo ya fue procesado anteriormente.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$text = $this->extract_text_from_file( $upload['file'], $upload['type'] );
		if ( empty( $text ) ) {
			wp_delete_attachment( $attachment_id, true );
			$message = ! empty( $this->last_extraction_error ) ? $this->last_extraction_error : 'No fue posible extraer texto del archivo.';
			$this->set_training_notice( 'error', $message );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		update_post_meta( $attachment_id, 'chatbot_training_source', 'upload' );
		update_post_meta( $attachment_id, 'chatbot_file_hash', $hash );

		$indexer = new Chat_Bot_Indexer();
		$chunk_limit = $this->get_training_max_chunks();
		$result = $indexer->index_document( $attachment_id, $text, 'file', $chunk_limit );
		$total = isset( $result['chunks_total'] ) ? (int) $result['chunks_total'] : 0;
		$embedded = isset( $result['chunks_embedded'] ) ? (int) $result['chunks_embedded'] : 0;
		$error = isset( $result['error'] ) ? $result['error'] : '';

		update_post_meta( $attachment_id, 'chatbot_chunk_count', $embedded );
		update_post_meta( $attachment_id, 'chatbot_indexed_at', current_time( 'mysql' ) );

		if ( $embedded <= 0 ) {
			$message = 'No se pudieron generar embeddings.';
			if ( ! empty( $error ) ) {
				$message .= ' Error: ' . $error;
			}
			$this->set_training_notice( 'error', $message );
		} elseif ( $embedded < $total ) {
			$message = 'Archivo indexado parcialmente. Chunks: ' . $embedded . ' de ' . $total . '.';
			if ( ! empty( $error ) ) {
				$message .= ' Ultimo error: ' . $error;
			}
			$this->set_training_notice( 'warning', $message );
		} else {
			$this->set_training_notice( 'success', 'Archivo indexado correctamente. Chunks: ' . $embedded . '.' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
		exit;
	}

	public function handle_training_file_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No autorizado.' );
		}

		check_admin_referer( 'chatbot_delete_training_file' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		if ( $attachment_id <= 0 ) {
			$this->set_training_notice( 'error', 'Archivo no valido.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$source = get_post_meta( $attachment_id, 'chatbot_training_source', true );
		if ( $source !== 'upload' ) {
			$this->set_training_notice( 'error', 'El archivo no pertenece al entrenamiento.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$indexer = new Chat_Bot_Indexer();
		$indexer->delete_document_embeddings( $attachment_id, 'file' );

		$deleted = wp_delete_attachment( $attachment_id, true );
		if ( ! $deleted ) {
			$this->set_training_notice( 'error', 'No se pudo eliminar el archivo.' );
			wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
			exit;
		}

		$this->set_training_notice( 'success', 'Archivo eliminado y embeddings removidos.' );
		wp_safe_redirect( admin_url( 'admin.php?page=chat-bot-settings' ) );
		exit;
	}

	private function get_training_allowed_mimes() {
		$mimes = array(
			'txt' => 'text/plain',
			'md' => 'text/markdown',
			'csv' => 'text/csv',
			'pdf' => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
		return apply_filters( 'chatbot_training_allowed_mimes', $mimes );
	}

	private function get_training_max_file_size() {
		$limit = 15 * MB_IN_BYTES;
		$wp_limit = wp_max_upload_size();
		if ( $wp_limit <= 0 ) {
			$wp_limit = $limit;
		}
		$size = (int) min( $limit, $wp_limit );
		return (int) apply_filters( 'chatbot_training_max_file_size', $size );
	}

	private function get_training_max_chunks() {
		$limit = apply_filters( 'chatbot_training_max_chunks', 200 );
		return (int) max( 1, $limit );
	}

	private function create_training_attachment( $upload ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_path = $upload['file'];
		$attachment = array(
			'post_mime_type' => $upload['type'],
			'post_title' => sanitize_file_name( wp_basename( $file_path ) ),
			'post_content' => '',
			'post_status' => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );
		if ( ! $attachment_id ) {
			return 0;
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file_path ) );
		return $attachment_id;
	}

	private function find_existing_training_file( $hash ) {
		$existing = get_posts( array(
			'post_type' => 'attachment',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => 'chatbot_training_source',
					'value' => 'upload',
				),
				array(
					'key' => 'chatbot_file_hash',
					'value' => $hash,
				),
			),
		) );

		return ! empty( $existing ) ? (int) $existing[0] : 0;
	}

	private function extract_text_from_file( $file_path, $mime_type ) {
		$this->last_extraction_error = '';
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$max_bytes = $this->get_training_max_file_size();

		if ( in_array( $extension, array( 'txt', 'md', 'csv' ), true ) ) {
			return $this->extract_text_from_text_file( $file_path, $extension, $max_bytes );
		}

		if ( $extension === 'docx' || $mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) {
			return $this->extract_text_from_docx( $file_path );
		}

		if ( $extension === 'pdf' || $mime_type === 'application/pdf' ) {
			return $this->extract_text_from_pdf( $file_path );
		}

		return '';
	}

	private function extract_text_from_text_file( $file_path, $extension, $max_bytes ) {
		if ( $extension === 'csv' ) {
			return $this->extract_text_from_csv( $file_path );
		}

		$size = filesize( $file_path );
		$length = $max_bytes;
		if ( $size !== false && $size > 0 ) {
			$length = min( $size, $max_bytes );
		}

		$content = file_get_contents( $file_path, false, null, 0, $length );
		if ( $content === false ) {
			return '';
		}

		$content = preg_replace( '/\x00+/', '', $content );
		return (string) $content;
	}

	private function extract_text_from_csv( $file_path ) {
		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return '';
		}

		$rows = array();
		$max_rows = (int) apply_filters( 'chatbot_training_csv_max_rows', 0 );
		$count = 0;

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$rows[] = implode( ' | ', array_map( 'trim', $data ) );
			$count++;
			if ( $max_rows > 0 && $count >= $max_rows ) {
				break;
			}
		}

		fclose( $handle );
		return implode( "\n", $rows );
	}

	private function extract_text_from_docx( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) !== true ) {
			return '';
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( empty( $xml ) ) {
			return '';
		}

		$xml = str_replace( array( '</w:p>', '</w:tr>' ), "\n", $xml );
		$text = wp_strip_all_tags( $xml );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		return trim( $text );
	}

	private function extract_text_from_pdf( $file_path ) {
		if ( function_exists( 'shell_exec' ) ) {
			$binary = trim( (string) shell_exec( 'command -v pdftotext' ) );
			if ( ! empty( $binary ) ) {
				$cmd = 'pdftotext -q ' . escapeshellarg( $file_path ) . ' -';
				$output = shell_exec( $cmd );
				if ( ! empty( $output ) ) {
					return trim( $output );
				}
			}
		}

		$vendor_autoload = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/vendor/smalot/pdfparser/autoload.php';
		if ( file_exists( $vendor_autoload ) ) {
			require_once $vendor_autoload;
		}

		if ( class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			$parser = new \Smalot\PdfParser\Parser();
			$pdf = $parser->parseFile( $file_path );
			return trim( $pdf->getText() );
		}

		if ( ! class_exists( 'Chat_Bot_Pdf_Parser' ) ) {
			$parser_path = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-chat-bot-pdf-parser.php';
			if ( file_exists( $parser_path ) ) {
				require_once $parser_path;
			}
		}

		if ( class_exists( 'Chat_Bot_Pdf_Parser' ) ) {
			$parser = new Chat_Bot_Pdf_Parser();
			$text = $parser->extract_text( $file_path );
			if ( ! empty( $text ) ) {
				return $text;
			}
		}

		$this->last_extraction_error = 'No fue posible extraer texto del PDF con pdftotext ni con el parser integrado.';
		return '';
	}

	private function render_training_documents_list() {
		$docs = get_posts( array(
			'post_type' => 'attachment',
			'posts_per_page' => 10,
			'post_status' => 'inherit',
			'meta_key' => 'chatbot_training_source',
			'meta_value' => 'upload',
			'orderby' => 'date',
			'order' => 'DESC',
		) );

		if ( empty( $docs ) ) {
			return;
		}

		echo '<h3>Archivos indexados recientemente</h3>';
		echo '<table class="widefat striped"><thead><tr><th>Archivo</th><th>Tipo</th><th>Chunks</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>';

		foreach ( $docs as $doc ) {
			$chunks = get_post_meta( $doc->ID, 'chatbot_chunk_count', true );
			echo '<tr>';
			echo '<td>' . esc_html( get_the_title( $doc->ID ) ) . '</td>';
			echo '<td>' . esc_html( $doc->post_mime_type ) . '</td>';
			echo '<td>' . esc_html( $chunks ? $chunks : '0' ) . '</td>';
			echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i', $doc->ID ) ) . '</td>';
			echo '<td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Eliminar este archivo y sus embeddings?\');">';
			wp_nonce_field( 'chatbot_delete_training_file' );
			echo '<input type="hidden" name="action" value="chatbot_delete_training_file">';
			echo '<input type="hidden" name="attachment_id" value="' . esc_attr( $doc->ID ) . '">';
			submit_button( 'Eliminar', 'delete', 'submit', false );
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

}
