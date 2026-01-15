<?php

/**
 * Content indexing functionality for the chatbot
 *
 * @since      1.0.2
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */

class Chat_Bot_Indexer {

    private $table_name;
    private $last_embedding_error = '';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'chatbot_embeddings';
    }

    /**
     * Index a post's content
     */
    public function index_post($post_id) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $this->index_woocommerce_product($post_id);
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
     * Index arbitrary document text (uploaded files)
     */
    public function index_document($source_id, $text, $source_type = 'file', $max_chunks = 0) {
        if (empty($text)) {
            return array(
                'chunks_total' => 0,
                'chunks_embedded' => 0,
                'error' => 'El contenido del archivo esta vacio.',
            );
        }

        $this->delete_embeddings($source_type, $source_id);

        $chunks = $this->chunk_text($text);
        if ($max_chunks > 0) {
            $chunks = array_slice($chunks, 0, $max_chunks);
        }

        $total = count($chunks);
        $count = 0;
        $last_error = '';
        foreach ($chunks as $chunk) {
            $embedding = $this->generate_embedding($chunk);
            if ($embedding) {
                $this->store_embedding($source_type, $source_id, $chunk, $embedding);
                $count++;
            } else {
                $last_error = $this->last_embedding_error;
                if (!empty($last_error)) {
                    break;
                }
            }
        }

        return array(
            'chunks_total' => $total,
            'chunks_embedded' => $count,
            'error' => $last_error,
        );
    }

    /**
     * Index all public content for the site
     */
    public function index_all_content() {
        $this->clear_embeddings(array('file'));
        $this->index_site_metadata();
        $this->index_all_posts();
        $this->index_taxonomies();
    }

    /**
     * Index all public posts/pages/products
     */
    private function index_all_posts() {
        $post_types = get_post_types(['public' => true], 'names');
        $exclude = ['attachment', 'nav_menu_item', 'revision'];
        $post_types = array_diff($post_types, $exclude);

        foreach ($post_types as $post_type) {
            $paged = 1;
            do {
                $query = new WP_Query([
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => 50,
                    'paged' => $paged,
                    'fields' => 'ids',
                ]);

                if (empty($query->posts)) {
                    break;
                }

                foreach ($query->posts as $post_id) {
                    $this->index_post($post_id);
                }

                $paged++;
            } while ($paged <= $query->max_num_pages);
        }
    }

    /**
     * Index all public taxonomies and terms
     */
    public function index_term($term_id, $tt_id = null, $taxonomy = '') {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return;
        }

        $this->delete_embeddings('term', $term->term_id);

        $text = 'Taxonomía: ' . $term->taxonomy . ' Término: ' . $term->name . ' ' . $term->description;
        $chunks = $this->chunk_text($text);
        foreach ($chunks as $chunk) {
            $embedding = $this->generate_embedding($chunk);
            if ($embedding) {
                $this->store_embedding('term', $term->term_id, $chunk, $embedding);
            }
        }
    }

    private function index_taxonomies() {
        $taxonomies = get_taxonomies(['public' => true], 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ]);

            if (is_wp_error($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $this->index_term($term->term_id, null, $taxonomy);
            }
        }
    }

    /**
     * Index site-level metadata
     */
    private function index_site_metadata() {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $front_page_id = (int) get_option('page_on_front');
        $front_page_title = $front_page_id ? get_the_title($front_page_id) : '';

        $text = "Sitio: {$site_name}. Descripción: {$site_description}.";
        if (!empty($front_page_title)) {
            $text .= " Página principal: {$front_page_title}.";
        }

        $chunks = $this->chunk_text($text);
        foreach ($chunks as $chunk) {
            $embedding = $this->generate_embedding($chunk);
            if ($embedding) {
                $this->store_embedding('site', 0, $chunk, $embedding);
            }
        }
    }

    /**
     * Index WooCommerce product including stock and pricing
     */
    private function index_woocommerce_product($product_id) {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $this->delete_embeddings('product', $product->get_id());

        $content = $product->get_name() . ' ' . $product->get_description() . ' ' . $product->get_short_description();
        $content .= ' Precio: ' . $product->get_price() . ' ' . get_woocommerce_currency_symbol();

        $sku = $product->get_sku();
        if (!empty($sku)) {
            $content .= ' SKU: ' . $sku;
        }

        $stock_status = $product->get_stock_status();
        $stock_qty = $product->get_stock_quantity();
        if ($product->managing_stock() && $stock_qty !== null) {
            $content .= ' Stock: ' . $stock_qty;
        } else {
            $content .= ' Stock: ' . $stock_status;
        }

        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $content .= ' Variación: ' . wp_json_encode($variation['attributes']) . ' Precio: ' . $variation['display_price'];
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
        $this->last_embedding_error = '';
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

        if (empty($providers)) {
            $this->last_embedding_error = 'No hay claves de OpenAI o Google AI configuradas.';
            return false;
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
                $embedding = $this->generate_google_embedding($text, $google_key, 'RETRIEVAL_DOCUMENT');
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

        if (is_wp_error($response)) {
            $this->last_embedding_error = $response->get_error_message();
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error']['message'])) {
            $this->last_embedding_error = $body['error']['message'];
            return false;
        }

        $embedding = $body['data'][0]['embedding'] ?? null;
        if ($embedding === null) {
            $this->last_embedding_error = 'Respuesta invalida del servicio de embeddings.';
            return false;
        }

        return $embedding;
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

        if (is_wp_error($response)) {
            $this->last_embedding_error = $response->get_error_message();
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($payload['error']['message'])) {
            $this->last_embedding_error = $payload['error']['message'];
            return false;
        }

        $embedding = $payload['embedding']['values'] ?? null;
        if ($embedding === null) {
            $this->last_embedding_error = 'Respuesta invalida del servicio de embeddings (Google).';
            return false;
        }

        return $embedding;
    }

    public function get_last_embedding_error() {
        return $this->last_embedding_error;
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

    public function delete_document_embeddings($source_id, $source_type = 'file') {
        $this->delete_embeddings($source_type, $source_id);
    }

    private function clear_embeddings($exclude_source_types = array()) {
        global $wpdb;
        if (empty($exclude_source_types)) {
            $wpdb->query("DELETE FROM {$this->table_name}");
            return;
        }

        $placeholders = implode(',', array_fill(0, count($exclude_source_types), '%s'));
        $query = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE source_type NOT IN ($placeholders)",
            $exclude_source_types
        );
        $wpdb->query($query);
    }
}
