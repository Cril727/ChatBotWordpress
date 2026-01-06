<?php

/**
 * Chat processing functionality for the chatbot
 *
 * @since      1.0.0
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
        // Generate embedding for the query
        $query_embedding = $this->generate_embedding($message);
        if (!$query_embedding) {
            return 'Lo siento, no pude procesar tu mensaje.';
        }

        // Search for similar content
        $relevant_chunks = $this->search_similar($query_embedding, $current_post_id);

        // Generate response using AI
        return $this->generate_response($message, $relevant_chunks);
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
        $api_key = get_option('chatbot_openai_api_key');
        if (!$api_key) return 'API key no configurada.';

        // Build context
        $context = '';
        foreach ($relevant_chunks as $chunk) {
            $context .= $chunk['chunk'] . "\n";
        }

        $prompt = "Contexto del sitio web:\n" . $context . "\n\nPregunta del usuario: " . $message . "\n\nResponde basándote en el contexto proporcionado.";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asistente útil que responde preguntas basadas en el contenido del sitio web.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return 'Error al generar respuesta.';

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? 'No pude generar una respuesta.';
    }
}