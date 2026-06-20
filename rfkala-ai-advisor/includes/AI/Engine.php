<?php

namespace RfkalaAiAdvisor\AI;

use RfkalaAiAdvisor\WooCommerce\Client as WcClient;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Engine {

    private $knowledge_base = [];

    public function __construct() {
        $this->load_knowledge_base();
    }

    private function load_knowledge_base() {
        $json_file = RFKALA_AI_ADVISOR_PLUGIN_DIR . 'faq_knowledge.json';
        if ( file_exists( $json_file ) ) {
            $data = file_get_contents( $json_file );
            $decoded = json_decode( $data, true );
            if ( is_array( $decoded ) ) {
                $this->knowledge_base = $decoded;
            }
        }
    }

    public function process_message( $message ) {
        // Step 1: Search KB based on keywords
        $matched_topic = $this->find_matching_topic( $message );
        $context = "";

        if ( $matched_topic ) {
            $context = "Here is the factual data strictly from the knowledge base for this topic (" . $matched_topic['topic'] . "):\n";
            $context .= "NOVICE LEVEL: " . $matched_topic['answers']['novice'] . "\n";
            $context .= "INTERMEDIATE LEVEL: " . $matched_topic['answers']['intermediate'] . "\n";
            $context .= "EXPERT LEVEL: " . $matched_topic['answers']['expert'] . "\n";

            // Optionally, try fetching WooCommerce data for keywords to attach
            $wc_client = new WcClient();
            // Just use the first keyword for a simple product search
            if ( !empty($matched_topic['keywords']) ) {
                $products = $wc_client->search_products( $matched_topic['keywords'][0] );
                if ( $products ) {
                    $context .= "\nLIVE STORE DATA (from WooCommerce):\n";
                    foreach( $products as $prod ) {
                        $context .= "- {$prod['name']} | Price: {$prod['price']} | Stock: {$prod['stock']} | Link: {$prod['url']}\n";
                    }
                }
            }
        } else {
            $context = "No direct knowledge base entry found. Kindly inform the user that you only answer questions related to RF Networking based on the provided data, and offer to transfer them to a human agent.";
        }

        // Step 2: Build the System Prompt
        $system_prompt = "You are a Senior RF Engineer and Technical Advisor at RFKala.ir. \n";
        $system_prompt .= "Your job is to read the user's prompt, determine their technical level (novice, intermediate, expert), and answer strictly using the provided context that matches their level.\n";
        $system_prompt .= "Never hallucinate outside the provided context. If no context is provided, apologize and refer them to a human.\n";
        $system_prompt .= "Reply in Persian (Farsi), as the store's primary audience is Iranian.\n";
        $system_prompt .= "--- CONTEXT ---\n" . $context;

        // Step 3: Call AI API
        return $this->call_ai_api( $system_prompt, $message );
    }

    private function find_matching_topic( $message ) {
        $message_lower = strtolower( $message );
        foreach ( $this->knowledge_base as $entry ) {
            foreach ( $entry['keywords'] as $keyword ) {
                if ( strpos( $message_lower, strtolower( $keyword ) ) !== false ) {
                    return $entry;
                }
            }
        }
        return null;
    }

    private function call_ai_api( $system_prompt, $user_message ) {
        $endpoint = get_option( 'rfkala_ai_api_endpoint' );
        $api_key  = get_option( 'rfkala_ai_api_key' );

        // Fallback to hardcoded constants
        if ( empty( $endpoint ) ) $endpoint = RFKALA_DEFAULT_AI_ENDPOINT;

        // Note: $api_key relies on get_option which is populated by Settings.php defaults.

        if ( empty( $endpoint ) || empty( $api_key ) ) {
            return "Error: AI API not configured properly.";
        }

        $payload = [
            'model' => 'gpt-3.5-turbo', // Or whatever model GapGPT uses
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => $user_message
                ]
            ],
            'temperature' => 0.3
        ];

        $response = wp_remote_post( $endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body'    => json_encode( $payload ),
            'timeout' => 30
        ] );

        if ( is_wp_error( $response ) ) {
            return "Error communicating with the AI service: " . $response->get_error_message();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return $data['choices'][0]['message']['content'];
        }

        return "Sorry, an unexpected response format was received from the AI.";
    }
}
