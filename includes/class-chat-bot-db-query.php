<?php

/**
 * Direct MySQL database querying for the chatbot
 *
 * @since      1.0.1
 * @package    Chat_Bot
 * @subpackage Chat_Bot/includes
 */

class Chat_Bot_DB_Query {

    private $connection;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $host = get_option('chatbot_db_host', DB_HOST);
        $user = get_option('chatbot_db_user', DB_USER);
        $pass = get_option('chatbot_db_pass', DB_PASSWORD);
        $db = get_option('chatbot_db_name', DB_NAME);

        $this->connection = new mysqli($host, $user, $pass, $db);
        if ($this->connection->connect_error) {
            error_log('ChatBot DB Connection failed: ' . $this->connection->connect_error);
            $this->connection = null;
        }
    }

    /**
     * Execute a safe SELECT query
     */
    public function execute_safe_query($query, $params = []) {
        if (!$this->connection) return false;

        // Only allow SELECT queries for security
        if (!preg_match('/^SELECT/i', trim($query))) {
            return false;
        }

        $stmt = $this->connection->prepare($query);
        if (!$stmt) return false;

        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    /**
     * Get custom queries from settings
     */
    public function get_custom_queries() {
        $queries_str = get_option('chatbot_custom_queries', '');
        $queries = array_filter(array_map('trim', explode("\n", $queries_str)));
        return $queries;
    }

    /**
     * Execute custom queries and return formatted results
     */
    public function execute_custom_queries() {
        $queries = $this->get_custom_queries();
        $results = [];

        foreach ($queries as $query) {
            $data = $this->execute_safe_query($query);
            if ($data) {
                $results[] = [
                    'query' => $query,
                    'data' => $data
                ];
            }
        }

        return $results;
    }

    /**
     * Format query results as text for indexing
     */
    public function format_results_for_indexing($results) {
        $texts = [];

        foreach ($results as $result) {
            $query = $result['query'];
            $data = $result['data'];

            $text = "Consulta: $query\nResultados:\n";
            foreach ($data as $row) {
                $text .= json_encode($row) . "\n";
            }
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * Close connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}