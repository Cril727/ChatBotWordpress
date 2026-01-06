<?php

/**
 * Chat processing functionality for the chatbot
 *
 * @since      1.0.1
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
     * Process a chat message and return response
     */
    public function process_message($message, $current_post_id = null) {
        $api_key = get_option('chatbot_openai_api_key');
        error_log('ChatBot Debug: Processing message: ' . $message . ', Post ID: ' . $current_post_id);
        $relevant_chunks = [];

        if ($api_key) {
            error_log('ChatBot Debug: API key available, generating embedding');
            // Generate embedding for the query
            $query_embedding = $this->generate_embedding($message);
            if ($query_embedding) {
                error_log('ChatBot Debug: Embedding generated successfully');
                // Search for similar content
                $relevant_chunks = $this->search_similar($query_embedding, $current_post_id);
                error_log('ChatBot Debug: Found ' . count($relevant_chunks) . ' relevant chunks');
            } else {
                error_log('ChatBot Debug: Failed to generate embedding');
            }
        } else {
            error_log('ChatBot Debug: No API key, skipping embeddings');
        }

        // If no relevant chunks found or no API key, use site-wide content as fallback
        if (empty($relevant_chunks)) {
            error_log('ChatBot Debug: Using site content fallback');
            $site_content = $this->get_site_content();
            if ($site_content) {
                $relevant_chunks = [['chunk' => $site_content, 'similarity' => 1.0]];
            }
        }

        // Generate response using AI or basic fallback
        return $this->generate_response($message, $relevant_chunks);
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
        $api_key = get_option('chatbot_openai_api_key');
        if (!$api_key) return false;

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'text-embedding-ada-002',
                'input' => $text,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'][0]['embedding'] ?? false;
    }

    /**
     * Generate response using OpenAI Chat Completion
     */
    private function generate_response($message, $relevant_chunks) {
        // Build context
        $context = '';
        foreach ($relevant_chunks as $chunk) {
            $context .= $chunk['chunk'] . "\n";
        }

        $prompt = "Contexto del sitio web:\n" . $context . "\n\nPregunta del usuario: " . $message . "\n\nResponde basándote en el contexto proporcionado.";

        // Try Google first if key is set
        $google_key = get_option('chatbot_google_api_key');
        if (!empty($google_key)) {
            error_log('ChatBot Debug: Trying Google AI first');
            $result = $this->generate_google_response($prompt, $message, $context);
            if ($result !== false) { // Assuming false means failed
                return $result;
            }
        }

        // Try OpenAI if key is set
        $openai_key = get_option('chatbot_openai_api_key');
        if (!empty($openai_key)) {
            error_log('ChatBot Debug: Trying OpenAI as fallback');
            $result = $this->generate_openai_response($prompt, $message, $context);
            if ($result !== false) {
                return $result;
            }
        }

        // Fallback to basic
        error_log('ChatBot Debug: Using basic response');
        return $this->generate_basic_response($message, $context);
    }

    private function generate_openai_response($prompt, $message, $context) {
        $api_key = get_option('chatbot_openai_api_key');
        $model = get_option('chatbot_openai_model', 'gpt-3.5-turbo');
        error_log('ChatBot Debug: OpenAI API key present: ' . (!empty($api_key) ? 'Yes' : 'No') . ', Model: ' . $model);

        if (!$api_key) {
            error_log('ChatBot Debug: No OpenAI API key, using basic response');
            return $this->generate_basic_response($message, $context);
        }

        error_log('ChatBot Debug: Sending prompt to OpenAI: ' . substr($prompt, 0, 200) . '...');

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
            error_log('ChatBot Debug: WP Error in OpenAI call: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        error_log('ChatBot Debug: OpenAI response status: ' . wp_remote_retrieve_response_code($response));
        error_log('ChatBot Debug: OpenAI response body: ' . print_r($body, true));

        if (isset($body['error'])) {
            error_log('ChatBot Debug: OpenAI API error: ' . $body['error']['message']);
            return false;
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            error_log('ChatBot Debug: No content in OpenAI response');
            return false;
        }

        return $content;
    }

    private function generate_google_response($prompt, $message, $context) {
        $api_key = get_option('chatbot_google_api_key');
        $model = get_option('chatbot_google_model', 'gemini-pro');
        error_log('ChatBot Debug: Google API key present: ' . (!empty($api_key) ? 'Yes' : 'No') . ', Model: ' . $model);

        if (!$api_key) {
            error_log('ChatBot Debug: No Google API key, using basic response');
            return $this->generate_basic_response($message, $context);
        }

        error_log('ChatBot Debug: Sending prompt to Google AI: ' . substr($prompt, 0, 200) . '...');

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
            error_log('ChatBot Debug: WP Error in Google call: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        error_log('ChatBot Debug: Google response status: ' . wp_remote_retrieve_response_code($response));
        error_log('ChatBot Debug: Google response body: ' . print_r($body, true));

        if (isset($body['error'])) {
            error_log('ChatBot Debug: Google API error: ' . $body['error']['message']);
            return false;
        }

        $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($content === null) {
            error_log('ChatBot Debug: No content in Google response');
            return false;
        }

        return $content;
    }

    /**
     * Generate a basic response when API key is not available
     */
    private function generate_basic_response($message, $context) {
        // Simple keyword-based response generation
        $message_lower = strtolower($message);
        $context_lower = strtolower($context);

        // Basic responses for common questions
        if (strpos($message_lower, 'hola') !== false || strpos($message_lower, 'hello') !== false) {
            return '¡Hola! Soy el asistente del sitio web. ¿En qué puedo ayudarte?';
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

        if (!empty($found_posts)) {
            $response = "¡Hola! Encontré algo interesante en nuestro sitio:\n\n";
            // Show top 1 most relevant post/page
            $content = $found_posts[0]['content'];
            // Extract title and content
            if (strpos($content, ': ') !== false) {
                list($title, $body) = explode(': ', $content, 2);
                $response .= "**" . $title . "**\n" . substr($body, 0, 150) . "...\n\n¿Te gustaría saber más?";
            } else {
                $response .= substr($content, 0, 200) . "...\n\n¿Te ayudo con algo más?";
            }
            return $response;
        }

        return '¡Hola! No encontré información exacta sobre eso, pero nuestro sitio tiene contenido interesante. ¿Puedes contarme más sobre lo que buscas?';
    }
}