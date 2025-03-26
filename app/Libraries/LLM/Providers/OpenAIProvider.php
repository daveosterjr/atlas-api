<?php

namespace App\Libraries\LLM\Providers;

use App\Config\LLM as LLMConfig;
use App\Libraries\LLM\LLMProviderInterface;
use Exception;

class OpenAIProvider implements LLMProviderInterface {
    /**
     * @var LLMConfig The configuration
     */
    protected $config;

    /**
     * @var string The base API URL
     */
    protected $apiUrl = 'https://api.openai.com/v1';

    /**
     * Constructor
     */
    public function __construct(LLMConfig $config) {
        $this->config = $config;
    }

    /**
     * Perform a chat completion
     *
     * @param array $messages The messages in the conversation
     * @param array $options Additional options for the request
     * @return array The response from the LLM
     */
    public function chatCompletion(array $messages, array $options = []): array {
        $endpoint = '/chat/completions';

        $data = [
            'model' => $options['model'] ?? $this->config->defaultModel,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->config->temperature,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
        ];

        // Add any additional options
        foreach ($options as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $this->makeRequest($endpoint, $data);
    }

    /**
     * Perform a text completion
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options for the request
     * @return array The response from the LLM
     */
    public function textCompletion(string $prompt, array $options = []): array {
        // OpenAI is phasing out completions, so we'll use chat completions
        $messages = [['role' => 'user', 'content' => $prompt]];

        return $this->chatCompletion($messages, $options);
    }

    /**
     * Generate embeddings for a text
     *
     * @param string $text The text to generate embeddings for
     * @return array The embeddings
     */
    public function generateEmbeddings(string $text): array {
        $endpoint = '/embeddings';

        $data = [
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ];

        return $this->makeRequest($endpoint, $data);
    }

    /**
     * Make a request to the OpenAI API
     *
     * @param string $endpoint The API endpoint
     * @param array $data The request data
     * @return array The response data
     */
    protected function makeRequest(string $endpoint, array $data): array {
        $ch = curl_init($this->apiUrl . $endpoint);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->apiKey,
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $responseData['error']['message'] ?? 'Unknown API error';
            throw new Exception('API Error: ' . $errorMessage);
        }

        return $responseData;
    }
}
