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
            return 0;
        }

        $this->delete_embeddings($source_type, $source_id);

        $chunks = $this->chunk_text($text);
        if ($max_chunks > 0) {
            $chunks = array_slice($chunks, 0, $max_chunks);
        }

        $count = 0;
        foreach ($chunks as $chunk) {
            $embedding = $this->generate_embedding($chunk);
            if ($embedding) {
                $this->store_embedding($source_type, $source_id, $chunk, $embedding);
                $count++;
            }
        }

        return $count;
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
