<?php

/**
 * Chat processing functionality for the chatbot
 *
 * @since      1.0.2
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */

class Chat_Bot_Chat {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'chatbot_embeddings';
    }

    /**
     * Check if the site is an e-commerce site
     */
    private function is_ecommerce_site() {
        return class_exists('WooCommerce');
    }

    /**
     * Search for products by name and return links
     */
    private function search_products($query) {
        if (!$this->is_ecommerce_site()) return [];

        $products = wc_get_products(array(
            's' => $query,
            'status' => 'publish',
            'limit' => 5,
        ));

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'name' => $product->get_name(),
                'url' => get_permalink($product->get_id()),
                'price' => $product->get_price(),
                'stock_qty' => $product->get_stock_quantity(),
                'stock_status' => $product->get_stock_status(),
            ];
        }

        return $results;
    }

    /**
     * Extract URLs from message
     */
    private function extract_urls($message) {
        $pattern = '/\bhttps?:\/\/[^\s]+/i';
        preg_match_all($pattern, $message, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Check if URL is internal to the site
     */
    private function is_internal_url($url) {
        $site_url = get_site_url();
        return strpos($url, $site_url) === 0;
    }

    private function normalize_session_id($session_id) {
        $session_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $session_id);
        if (strlen($session_id) > 64) {
            $session_id = substr($session_id, 0, 64);
        }
        return $session_id;
    }

    private function default_conversation_state() {
        return array(
            'topic' => '',
            'last_question' => '',
        );
    }

    private function get_conversation_state($session_id) {
        $session_id = $this->normalize_session_id($session_id);
        if ($session_id === '') {
            return $this->default_conversation_state();
        }

        $state = get_transient('chatbot_session_' . $session_id);
        if (!is_array($state)) {
            $state = $this->default_conversation_state();
        }

        return $state;
    }

    private function update_conversation_state($session_id, array $state) {
        $session_id = $this->normalize_session_id($session_id);
        if ($session_id === '') {
            return;
        }

        set_transient('chatbot_session_' . $session_id, $state, 30 * MINUTE_IN_SECONDS);
    }

    private function detect_topic_switch($message) {
        $message = strtolower($message);
        $phrases = array(
            'cambiar de tema',
            'cambiemos de tema',
            'otro tema',
            'nuevo tema',
            'pasemos a',
            'hablemos de',
        );

        foreach ($phrases as $phrase) {
            if (strpos($message, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_ambiguous_followup($message, $topic = '') {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if (preg_match('/\b20\d{2}\b/', $lower)) {
            return true;
        }

        if (strpos($lower, 'a que se refiere') !== false
            || strpos($lower, 'que se refiere') !== false
            || strpos($lower, 'que significa') !== false
            || strpos($lower, 'que paso') !== false
            || strpos($lower, 'que pasa') !== false
            || strpos($lower, 'y eso') !== false
            || strpos($lower, 'y esto') !== false
            || strpos($lower, 'eso') === 0
            || strpos($lower, 'esto') === 0
        ) {
            return true;
        }

        $clean = preg_replace('/[^a-z0-9\s]/', '', $lower);
        $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $word_count = count($words);

        $stopwords = array(
            'que', 'como', 'cual', 'cuales', 'quien', 'quienes', 'cuando', 'donde',
            'eso', 'esto', 'esa', 'ese', 'a', 'en', 'el', 'la', 'los', 'las', 'y',
            'pero', 'si', 'no', 'de', 'del', 'sobre', 'por', 'para', 'un', 'una',
            'me', 'te', 'se', 'lo', 'la', 'es', 'son', 'fue', 'era', 'y', 'o'
        );

        $meaningful = array();
        foreach ($words as $word) {
            if (!in_array($word, $stopwords, true)) {
                $meaningful[] = $word;
            }
        }

        if ($word_count <= 3 && count($meaningful) <= 1) {
            if (!empty($topic) && count($meaningful) === 1) {
                $token = $meaningful[0];
                if (stripos($topic, $token) === false) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    private function extract_title_from_chunk($chunk_text) {
        if (strpos($chunk_text, ': ') !== false) {
            list($title, $body) = explode(': ', $chunk_text, 2);
            $title = trim($title);
            if ($title !== '') {
                return $title;
            }
        }

        return '';
    }

    private function infer_topic_from_chunks($relevant_chunks) {
        foreach ($relevant_chunks as $chunk) {
            $source_id = isset($chunk['source_id']) ? (int) $chunk['source_id'] : 0;
            if ($source_id) {
                $title = get_the_title($source_id);
                if (!empty($title)) {
                    return $title;
                }
            }

            if (!empty($chunk['chunk'])) {
                $title = $this->extract_title_from_chunk($chunk['chunk']);
                if (!empty($title)) {
                    return $title;
                }
            }
        }

        return '';
    }

    private function filter_relevant_chunks($relevant_chunks, $min_similarity = 0.15) {
        $filtered = array();
        foreach ($relevant_chunks as $chunk) {
            if (!isset($chunk['similarity']) || $chunk['similarity'] >= $min_similarity) {
                $filtered[] = $chunk;
            }
        }

        return $filtered;
    }

    private function build_no_context_response($message, $conversation_state) {
        $topic = '';
        if (is_array($conversation_state) && !empty($conversation_state['topic'])) {
            $topic = $conversation_state['topic'];
        }

        if (!empty($topic)) {
            return 'No tengo informacion relacionada con eso en los documentos. Si quieres, seguimos con "' . $topic . '" o cambiamos de tema.';
        }

        return 'No tengo informacion relacionada con eso en los documentos indexados. Si puedes dar mas detalle o un tema, te ayudo.';
    }

    /**
     * Process a chat message and return response
     */
    public function process_message($message, $current_post_id = null, $current_url = '', $session_id = '') {
        $openai_key = get_option('chatbot_openai_api_key');
        $google_key = get_option('chatbot_google_api_key');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Processing message: ' . $message . ', Post ID: ' . $current_post_id);

        $state = $this->get_conversation_state($session_id);
        $topic = !empty($state['topic']) ? $state['topic'] : '';
        if ($this->detect_topic_switch($message)) {
            $topic = '';
            $state['topic'] = '';
        }

        $force_topic = (!empty($topic) && $this->is_ambiguous_followup($message, $topic));
        $query_text = $force_topic ? trim($message . ' ' . $topic) : $message;
        $relevant_chunks = [];

        if ($openai_key || $google_key) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: API key available, generating embedding');
            // Generate embedding for the query
            $query_embedding = $this->generate_embedding($query_text);
            if ($query_embedding) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Embedding generated successfully');
                // Search for similar content
                $relevant_chunks = $this->search_similar($query_embedding, $current_post_id);
                $relevant_chunks = $this->filter_relevant_chunks($relevant_chunks);
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Found ' . count($relevant_chunks) . ' relevant chunks');
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Failed to generate embedding');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: No API key, skipping embeddings');
        }

        // Check for URLs in message
        $message_urls = $this->extract_urls($message);
        if (!empty($message_urls)) {
            foreach ($message_urls as $url) {
                if ($this->is_internal_url($url)) {
                    $post_id = url_to_postid($url);
                    if ($post_id) {
                        $post = get_post($post_id);
                        if ($post) {
                            $additional_content = $post->post_title . ': ' . wp_strip_all_tags($post->post_content);
                            $relevant_chunks[] = ['chunk' => $additional_content, 'similarity' => 1.0, 'source_type' => 'url', 'source_id' => $post_id];
                        }
                    }
                }
            }
        }

        $no_context = empty($relevant_chunks);

        // If no embeddings are available, use site-wide content as a basic fallback
        if ($no_context && empty($openai_key) && empty($google_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Using site content fallback');
            $site_content = $this->get_site_content();
            if ($site_content) {
                $relevant_chunks = [['chunk' => $site_content, 'similarity' => 0.0]];
                $no_context = false;
            }
        }

        // Generate response using AI or basic fallback
        $response = $this->generate_response($message, $relevant_chunks, $current_url, $current_post_id, $state, $no_context);

        $state['last_question'] = $message;
        if (!$no_context && !$force_topic) {
            $new_topic = $this->infer_topic_from_chunks($relevant_chunks);
            if (!empty($new_topic)) {
                $state['topic'] = $new_topic;
            }
        }
        $this->update_conversation_state($session_id, $state);

        return $response;
    }

    /**
     * Get site-wide content as fallback (all published posts and pages)
     */
    private function get_site_content() {
        $content = '';

        // Get all published posts
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 50, // Limit to avoid too much content
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($posts as $post) {
            $content .= $post->post_title . ': ' . wp_strip_all_tags($post->post_content) . "\n\n";
        }

        // Get all published pages
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        foreach ($pages as $page) {
            $content .= $page->post_title . ': ' . wp_strip_all_tags($page->post_content) . "\n\n";
        }

        // If no content found, fallback to site description
        if (empty($content)) {
            $content = get_bloginfo('description') . ' ' . get_bloginfo('name');
        }

        return $content;
    }

    /**
     * Search for similar embeddings
     */
    private function search_similar($query_embedding, $current_post_id = null, $limit = 5) {
        global $wpdb;

        // Get all embeddings
        $embeddings = $wpdb->get_results("SELECT * FROM {$this->table_name}", ARRAY_A);

        $similarities = [];

        foreach ($embeddings as $emb) {
            $emb_vector = json_decode($emb['embedding'], true);
            if ($emb_vector) {
                $similarity = $this->cosine_similarity($query_embedding, $emb_vector);
                $similarities[] = [
                    'similarity' => $similarity,
                    'chunk' => $emb['chunk_text'],
                    'source_type' => $emb['source_type'],
                    'source_id' => $emb['source_id'],
                ];
            }
        }

        // Sort by similarity
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // If current post, boost its chunks
        if ($current_post_id) {
            foreach ($similarities as &$sim) {
                if ($sim['source_id'] == $current_post_id) {
                    $sim['similarity'] *= 1.2; // Boost
                }
            }
            usort($similarities, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
        }

        return array_slice($similarities, 0, $limit);
    }

    /**
     * Calculate cosine similarity
     */
    private function cosine_similarity($vec1, $vec2) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) return 0;

        return $dot_product / ($norm1 * $norm2);
    }

    /**
     * Generate embedding for text
     */
    private function generate_embedding($text) {
        $openai_key = get_option('chatbot_openai_api_key');
        $google_key = get_option('chatbot_google_api_key');
        $preferred = get_option('chatbot_embedding_provider', '');

        $providers = array();
        if ($preferred === 'openai' || $preferred === 'google') {
            $providers[] = $preferred;
        }

        if (empty($providers)) {
            if (!empty($openai_key)) {
                $providers[] = 'openai';
            }
            if (!empty($google_key)) {
                $providers[] = 'google';
            }
        } else {
            if ($providers[0] === 'openai' && !empty($google_key)) {
                $providers[] = 'google';
            }
            if ($providers[0] === 'google' && !empty($openai_key)) {
                $providers[] = 'openai';
            }
        }

        foreach ($providers as $provider) {
            if ($provider === 'openai' && empty($openai_key)) {
                continue;
            }
            if ($provider === 'google' && empty($google_key)) {
                continue;
            }

            if ($provider === 'openai') {
                $embedding = $this->generate_openai_embedding($text, $openai_key);
            } else {
                $embedding = $this->generate_google_embedding($text, $google_key, 'RETRIEVAL_QUERY');
            }

            if ($embedding !== false) {
                if ($provider !== $preferred) {
                    update_option('chatbot_embedding_provider', $provider);
                }
                return $embedding;
            }
        }

        return false;
    }

    private function generate_openai_embedding($text, $api_key) {
        $model = get_option('chatbot_openai_embedding_model', 'text-embedding-3-small');
        $model = apply_filters('chatbot_openai_embedding_model', $model);

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'input' => $text,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            return false;
        }

        return $body['data'][0]['embedding'] ?? false;
    }

    private function generate_google_embedding($text, $api_key, $task_type = '') {
        $model = get_option('chatbot_google_embedding_model', 'text-embedding-004');
        $model = apply_filters('chatbot_google_embedding_model', $model);
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':embedContent?key=' . $api_key;

        $body = array(
            'content' => array(
                'parts' => array(
                    array('text' => $text),
                ),
            ),
        );

        if (!empty($task_type)) {
            $body['taskType'] = $task_type;
        }

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) return false;

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($payload['error'])) {
            return false;
        }

        return $payload['embedding']['values'] ?? false;
    }

    /**
     * Generate response using OpenAI Chat Completion
     */
    private function generate_response($message, $relevant_chunks, $current_url = '', $current_post_id = 0, $conversation_state = array(), $no_context = false) {
        if ($no_context) {
            return $this->build_no_context_response($message, $conversation_state);
        }

        // Build context
        $context = '';
        foreach ($relevant_chunks as $chunk) {
            $context .= $chunk['chunk'] . "\n";
        }

        $is_ecommerce = $this->is_ecommerce_site();
        $site_type = $is_ecommerce ? 'comercio electronico' : 'sitio web general';
        $current_page_info = $this->get_page_context($current_post_id, $current_url);

        $active_topic = '';
        $last_question = '';
        if (is_array($conversation_state)) {
            $active_topic = !empty($conversation_state['topic']) ? $conversation_state['topic'] : '';
            $last_question = !empty($conversation_state['last_question']) ? $conversation_state['last_question'] : '';
        }

        $topic_line = $active_topic !== '' ? $active_topic : 'sin tema';
        $last_line = $last_question !== '' ? $last_question : 'sin pregunta previa';

        $prompt = "
         CONTEXTO DE CONVERSACION:
         - Tema activo: {$topic_line}
         - Ultima pregunta: {$last_line}
         - Mantener el tema activo hasta que el usuario pida cambiarlo.
         - Si la pregunta es ambigua, pedir una aclaracion breve.
         - Si el contexto no contiene la respuesta, decirlo claramente y ofrecer seguir con el tema actual o cambiarlo.

         SOBRE PRODUCTOS:
         - Este sitio es un {$site_type}.
         - Describe productos SOLO si el sitio es de comercio electronico.
         - Incluye unicamente caracteristicas, beneficios y precios cuando esten disponibles en el contexto.
         - No inventes informacion de productos.
         - Cuando sea util, incluye enlaces directos a paginas o productos del mismo sitio para guiar al usuario, nunca de otros comercios.
         - Para consultas especificas sobre productos, proporciona el enlace directo al producto si esta disponible en el contexto.

         OBJETIVO:
         - Ayudar al usuario a encontrar informacion rapidamente.
         - Facilitar la navegacion del sitio.
         - Incentivar la exploracion del contenido y, si aplica, la compra de productos.
         {$current_page_info}

         Contexto del sitio web:\n {$context} \n\nPregunta del usuario: {$message} \n\nResponde basandote en el contexto proporcionado.";

        // Try Google first if key is set
        $google_key = get_option('chatbot_google_api_key');
        if (!empty($google_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Trying Google AI first');
            $result = $this->generate_google_response($prompt, $message, $context, $current_url, $current_post_id, $conversation_state);
            if ($result !== false) { // Assuming false means failed
                return $result;
            }
        }

        // Try OpenAI if key is set
        $openai_key = get_option('chatbot_openai_api_key');
        if (!empty($openai_key)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Trying OpenAI as fallback');
            $result = $this->generate_openai_response($prompt, $message, $context, $current_url, $current_post_id, $conversation_state);
            if ($result !== false) {
                return $result;
            }
        }

        // Fallback to basic
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Using basic response');
        return $this->generate_basic_response($message, $context, $current_url, $current_post_id, $conversation_state, false);
    }

    private function generate_openai_response($prompt, $message, $context, $current_url = '', $current_post_id = 0, $conversation_state = array()) {
        $api_key = get_option('chatbot_openai_api_key');
        $model = get_option('chatbot_openai_model', 'gpt-3.5-turbo');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: OpenAI API key present: ' . (!empty($api_key) ? 'Yes' : 'No') . ', Model: ' . $model);

        if (!$api_key) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: No OpenAI API key, using basic response');
            return $this->generate_basic_response($message, $context, $current_url, $current_post_id, $conversation_state, false);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Sending prompt to OpenAI: ' . substr($prompt, 0, 200) . '...');

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asistente útil que responde preguntas basadas en el contenido del sitio web. '],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: WP Error in OpenAI call: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ChatBot Debug: OpenAI response status: ' . wp_remote_retrieve_response_code($response));
            error_log('ChatBot Debug: OpenAI response body: ' . print_r($body, true));
        }

        if (isset($body['error'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: OpenAI API error: ' . $body['error']['message']);
            return false;
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: No content in OpenAI response');
            return false;
        }

        return $content;
    }

    private function generate_google_response($prompt, $message, $context, $current_url = '', $current_post_id = 0, $conversation_state = array()) {
        $api_key = get_option('chatbot_google_api_key');
        $model = get_option('chatbot_google_model', 'gemini-pro');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Google API key present: ' . (!empty($api_key) ? 'Yes' : 'No') . ', Model: ' . $model);

        if (!$api_key) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: No Google API key, using basic response');
            return $this->generate_basic_response($message, $context, $current_url, $current_post_id, $conversation_state, false);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Sending prompt to Google AI: ' . substr($prompt, 0, 200) . '...');

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: WP Error in Google call: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ChatBot Debug: Google response status: ' . wp_remote_retrieve_response_code($response));
            error_log('ChatBot Debug: Google response body: ' . print_r($body, true));
        }

        if (isset($body['error'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: Google API error: ' . $body['error']['message']);
            return false;
        }

        $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($content === null) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('ChatBot Debug: No content in Google response');
            return false;
        }

        return $content;
    }

    /**
     * Generate a basic response when API key is not available
     */
    private function generate_basic_response($message, $context, $current_url = '', $current_post_id = 0, $conversation_state = array(), $no_context = false) {
        if ($no_context) {
            return $this->build_no_context_response($message, $conversation_state);
        }

        $topic = '';
        if (is_array($conversation_state) && !empty($conversation_state['topic'])) {
            $topic = $conversation_state['topic'];
        }

        // Simple keyword-based response generation
        $message_lower = strtolower($message);
        $context_lower = strtolower($context);

        if (strpos($message_lower, 'landing') !== false) {
            $is_landing = $this->is_landing_page($current_post_id, $current_url);
            return $is_landing
                ? 'Sí, esta página parece una landing page.'
                : 'No, no parece ser una landing page.';
        }

        // Basic responses for common questions
        if (strpos($message_lower, 'hola') !== false || strpos($message_lower, 'hello') !== false) {
            return 'Hola, soy el asistente del sitio. ¿En que tema quieres ayuda?';
        }

        if (strpos($message_lower, 'adiós') !== false || strpos($message_lower, 'bye') !== false) {
            return '¡Hasta luego! Gracias por visitarnos.';
        }

        // Split context into posts/pages for better search
        $content_parts = explode("\n\n", $context);
        $relevant_parts = [];

        // Try to find relevant posts/pages
        $words = explode(' ', $message_lower);
        $found_posts = [];

        foreach ($content_parts as $part) {
            if (empty(trim($part))) continue;

            $part_lower = strtolower($part);
            $relevance_score = 0;

            foreach ($words as $word) {
                if (strlen($word) > 3) { // Only check meaningful words
                    if (strpos($part_lower, $word) !== false) {
                        $relevance_score++;
                    }
                }
            }

            if ($relevance_score > 0) {
                $found_posts[] = [
                    'content' => $part,
                    'score' => $relevance_score
                ];
            }
        }

        // Sort by relevance
        usort($found_posts, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Check for product queries
        if ($this->is_ecommerce_site()) {
            $products = $this->search_products($message_lower);
            if (!empty($products)) {
                $response = "Encontre algunos productos relacionados:\n\n";
                foreach ($products as $product) {
                    $stock_text = '';
                    if ($product['stock_qty'] !== null) {
                        $stock_text = ' - Stock: ' . $product['stock_qty'];
                    } elseif (!empty($product['stock_status'])) {
                        $stock_text = ' - Stock: ' . $product['stock_status'];
                    }
                    $response .= "**" . $product['name'] . "** - Precio: $" . $product['price'] . $stock_text . "\nEnlace: " . $product['url'] . "\n\n";
                }
                $response .= "¿Te interesa alguno de estos productos?";
                return $response;
            }
        }

        if (!empty($found_posts)) {
            $response = "En el contenido del sitio encontre esto:\n\n";
            // Show top 1 most relevant post/page
            $content = $found_posts[0]['content'];
            // Extract title and content
            if (strpos($content, ': ') !== false) {
                list($title, $body) = explode(': ', $content, 2);
                $response .= "**" . $title . "**\n" . substr($body, 0, 150) . "...\n\n¿Quieres que profundice o seguimos con este tema?";
            } else {
                $response .= substr($content, 0, 200) . "...\n\n¿Quieres que profundice o seguimos con este tema?";
            }
            return $response;
        }

        if (!empty($topic)) {
            return 'No tengo informacion relacionada con eso en los documentos. Si quieres, seguimos con "' . $topic . '" o cambiamos de tema.';
        }

        return 'No tengo informacion relacionada con eso en los documentos indexados. ¿Puedes darme un poco mas de detalle o el tema que buscas?';
    }

    private function get_page_context($current_post_id = 0, $current_url = '') {
        $post_id = (int) $current_post_id;
        if (!$post_id && !empty($current_url)) {
            $post_id = (int) url_to_postid($current_url);
        }

        $parts = [];
        if (!empty($current_url)) {
            $parts[] = "Página actual: {$current_url}";
        }
        if ($post_id) {
            $title = get_the_title($post_id);
            $post_type = get_post_type($post_id);
            if (!empty($title)) {
                $parts[] = "Título: {$title}";
            }
            if (!empty($post_type)) {
                $parts[] = "Tipo: {$post_type}";
            }
        }

        $parts[] = 'Landing: ' . ($this->is_landing_page($post_id, $current_url) ? 'sí' : 'no');

        return implode('. ', $parts);
    }

    private function is_landing_page($post_id = 0, $current_url = '') {
        $post_id = (int) $post_id;
        if (!$post_id && !empty($current_url)) {
            $post_id = (int) url_to_postid($current_url);
        }

        if (!$post_id) {
            return false;
        }

        $template = get_post_meta($post_id, '_wp_page_template', true);
        $slug = get_post_field('post_name', $post_id);
        $title = get_the_title($post_id);

        $haystack = strtolower(trim($template . ' ' . $slug . ' ' . $title));
        return strpos($haystack, 'landing') !== false;
    }
}
