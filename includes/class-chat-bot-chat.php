<?php

class Chat_Bot_Chat
{

    private $table_name;
    private $max_context_chars = 3500;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'chatbot_embeddings';
    }

    public function process_message($message, $current_post_id = null)
    {
        $message = trim(wp_strip_all_tags($message));
        if ($message === '') {
            return 'Por favor escribe un mensaje válido.';
        }

        $relevant_chunks = [];

        $api_key = get_option('chatbot_openai_api_key');
        if (!empty($api_key)) {
            $query_embedding = $this->generate_embedding($message);
            if ($query_embedding) {
                $relevant_chunks = $this->search_similar($query_embedding, $current_post_id);
            }
        }

        if (empty($relevant_chunks)) {
            $site_content = $this->get_site_content();
            if ($site_content) {
                $relevant_chunks = [['chunk' => $site_content, 'similarity' => 1.0]];
            }
        }

        return $this->generate_response($message, $relevant_chunks);
    }

    private function get_site_content()
    {
        $cache = get_transient('chatbot_site_content');
        if ($cache !== false) return $cache;

        $content = '';

        $posts = get_posts([
            'post_status' => 'publish',
            'numberposts' => 30
        ]);

        foreach ($posts as $post) {
            $content .= $post->post_title . ': ' . wp_strip_all_tags($post->post_content) . "\n\n";
        }

        if ($content === '') {
            $content = get_bloginfo('description') . ' ' . get_bloginfo('name');
        }

        set_transient('chatbot_site_content', $content, HOUR_IN_SECONDS);
        return $content;
    }

    private function search_similar($query_embedding, $current_post_id = null, $limit = 5)
    {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT chunk_text, embedding, source_id FROM {$this->table_name}", ARRAY_A);
        if (!$rows) return [];

        $results = [];

        foreach ($rows as $row) {
            $vector = json_decode($row['embedding'], true);
            if (!is_array($vector)) continue;

            $sim = $this->cosine_similarity($query_embedding, $vector);
            if ($current_post_id && (int)$row['source_id'] === (int)$current_post_id) {
                $sim *= 1.2;
            }

            $results[] = [
                'chunk' => $row['chunk_text'],
                'similarity' => $sim
            ];
        }

        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($results, 0, $limit);
    }

    private function cosine_similarity($vec1, $vec2)
    {
        $length = min(count($vec1), count($vec2));
        if ($length === 0) return 0;

        $dot = $norm1 = $norm2 = 0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] ** 2;
            $norm2 += $vec2[$i] ** 2;
        }

        if ($norm1 == 0 || $norm2 == 0) return 0;
        return $dot / (sqrt($norm1) * sqrt($norm2));
    }

    private function generate_embedding($text)
    {
        $api_key = get_option('chatbot_openai_api_key');
        if (!$api_key) return false;

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'][0]['embedding'] ?? false;
    }

    private function generate_response($message, $relevant_chunks)
    {
        $context = '';

        foreach ($relevant_chunks as $chunk) {
            if (strlen($context) >= $this->max_context_chars) break;
            $context .= wp_strip_all_tags($chunk['chunk']) . "\n";
        }

        $prompt = "INSTRUCCIONES:
- Responde SOLO usando el contexto
- Si no hay información suficiente, dilo claramente

CONTEXTO:
{$context}

PREGUNTA:
{$message}";

        if ($key = get_option('chatbot_google_api_key')) {
            $r = $this->generate_google_response($prompt);
            if ($r !== false) return $r;
        }

        if ($key = get_option('chatbot_openai_api_key')) {
            $r = $this->generate_openai_response($prompt);
            if ($r !== false) return $r;
        }

        return $this->generate_basic_response($message, $context);
    }

    private function generate_openai_response($prompt)
    {
        $api_key = get_option('chatbot_openai_api_key');
        if (!$api_key) return false;

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => get_option('chatbot_openai_model', 'gpt-3.5-turbo'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asistente que responde únicamente con el contexto dado.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 400,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? false;
    }

    private function generate_google_response($prompt)
    {
        $api_key = get_option('chatbot_google_api_key');
        if (!$api_key) return false;

        $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=' . $api_key;

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]]
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['candidates'][0]['content']['parts'][0]['text'] ?? false;
    }

    private function generate_basic_response($message, $context)
    {
        if (stripos($message, 'hola') !== false) {
            return '¡Hola! ¿En qué puedo ayudarte?';
        }
        return 'No encontré información suficiente en el sitio para responder con precisión.';
    }
}
