<?php

/**
 * Content indexing functionality for the chatbot
 *
 * @since      1.0.1
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */

class Chat_Bot_Indexer {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'chatbot_embeddings';
    }

    /**
     * Index a post's content
     */
    public function index_post($post_id) {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_status, ['publish', 'private'])) {
            return;
        }

        // Delete existing embeddings for this post
        $this->delete_embeddings('post', $post_id);

        // Get content
        $content = $post->post_title . ' ' . $post->post_content;

        // Chunk content
        $chunks = $this->chunk_text($content);

        // Generate embeddings and store
        foreach ($chunks as $chunk) {
            $embedding = $this->generate_embedding($chunk);
            if ($embedding) {
                $this->store_embedding('post', $post_id, $chunk, $embedding);
            }
        }
    }

    /**
     * Index rendered content (for dynamic pages)
     */
    public function index_rendered_content($post_id, $rendered_content) {
        // Similar to index_post but with rendered content
        $this->delete_embeddings('rendered', $post_id);
        $chunks = $this->chunk_text($rendered_content);
        foreach ($chunks as $chunk) {
            $embedding = $this->generate_embedding($chunk);
            if ($embedding) {
                $this->store_embedding('rendered', $post_id, $chunk, $embedding);
            }
        }
    }

    /**
     * Index database metadata and custom queries
     */
    public function index_db_metadata() {
        // Index categories
        $categories = get_categories();
        foreach ($categories as $cat) {
            $text = 'Categoría: ' . $cat->name . ' ' . $cat->description;
            $chunks = $this->chunk_text($text);
            foreach ($chunks as $chunk) {
                $embedding = $this->generate_embedding($chunk);
                if ($embedding) {
                    $this->store_embedding('category', $cat->term_id, $chunk, $embedding);
                }
            }
        }

        // Index WooCommerce products if active
        if (class_exists('WooCommerce')) {
            $this->index_woocommerce_products();
        }

        // Index custom DB queries
        $this->index_custom_db_queries();
    }

    /**
     * Index WooCommerce products if active
     */
    private function index_woocommerce_products() {
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1, // All products
        ));

        foreach ($products as $product) {
            $this->delete_embeddings('product', $product->get_id());

            $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
            $content .= ' Precio: ' . $product->get_price() . ' ' . get_woocommerce_currency_symbol();

            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $content .= ' Variación: ' . $variation['attributes'] . ' Precio: ' . $variation['display_price'];
                }
            }

            $chunks = $this->chunk_text($content);
            foreach ($chunks as $chunk) {
                $embedding = $this->generate_embedding($chunk);
                if ($embedding) {
                    $this->store_embedding('product', $product->get_id(), $chunk, $embedding);
                }
            }
        }
    }

    /**
     * Index results from custom database queries
     */
    private function index_custom_db_queries() {
        $db_query = new Chat_Bot_DB_Query();
        $results = $db_query->execute_custom_queries();
        $texts = $db_query->format_results_for_indexing($results);

        foreach ($texts as $text) {
            $chunks = $this->chunk_text($text);
            foreach ($chunks as $chunk) {
                $embedding = $this->generate_embedding($chunk);
                if ($embedding) {
                    $this->store_embedding('db_query', 0, $chunk, $embedding);
                }
            }
        }

        $db_query->close();
    }

    /**
     * Chunk text into smaller pieces
     */
    private function chunk_text($text, $chunk_size = 500) {
        $text = wp_strip_all_tags($text);
        $words = explode(' ', $text);
        $chunks = [];
        $current_chunk = '';

        foreach ($words as $word) {
            if (strlen($current_chunk . ' ' . $word) > $chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = $word;
                }
            } else {
                $current_chunk .= ' ' . $word;
            }
        }

        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Generate embedding using OpenAI API
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
     * Store embedding in database
     */
    private function store_embedding($source_type, $source_id, $chunk_text, $embedding) {
        global $wpdb;
        $wpdb->insert($this->table_name, [
            'source_type' => $source_type,
            'source_id' => $source_id,
            'chunk_text' => $chunk_text,
            'embedding' => json_encode($embedding),
        ]);
    }

    /**
     * Delete embeddings for a source
     */
    private function delete_embeddings($source_type, $source_id) {
        global $wpdb;
        $wpdb->delete($this->table_name, [
            'source_type' => $source_type,
            'source_id' => $source_id,
        ]);
    }
}