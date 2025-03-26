<?php

namespace App\Libraries\LLMService\Providers;

use OpenAI;
use App\Libraries\LLMService\LLMProviderInterface;
use Exception;

/**
 * OpenAI Provider Implementation
 */
class OpenAIProvider implements LLMProviderInterface {
    /**
     * @var OpenAI\Client OpenAI client instance
     */
    private $client;

    /**
     * @var array Models that support image input
     */
    private array $imageModels = [
        'gpt-4-vision-preview',
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4o-search-preview',
    ];

    /**
     * @var array Models that support JSON response format
     */
    private array $jsonModels = [
        'gpt-4-turbo',
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-3.5-turbo',
        'gpt-3.5-turbo-1106',
        'gpt-4o-search-preview',
    ];

    /**
     * @var array Models that support web search capability
     */
    private array $searchModels = ['gpt-4o-search-preview'];

    /**
     * Initialize the client with the API key
     *
     * @param string $apiKey The API key for the LLM provider
     * @return void
     */
    public function initialize(string $apiKey): void {
        if (empty($apiKey)) {
            throw new Exception('OpenAI API key cannot be empty');
        }

        try {
            $this->client = OpenAI::client($apiKey);
            log_message('debug', 'OpenAI client initialized successfully');
        } catch (\Exception $e) {
            log_message('error', 'Failed to initialize OpenAI client: ' . $e->getMessage());
            throw new Exception('OpenAI client initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Send a completion request to the LLM
     *
     * @param array $messages The messages to send
     * @param string $model The model to use
     * @param float $temperature The temperature (0-2)
     * @param int $maxTokens The maximum number of tokens to generate
     * @param array|null $responseFormat The format of the response (e.g. json_object)
     * @return string The response text
     */
    public function complete(
        array $messages,
        string $model,
        float $temperature,
        int $maxTokens,
        ?array $responseFormat = null,
    ): string {
        $params = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
        ];

        // Only add temperature for non-search models
        if (!$this->supportsSearch($model)) {
            $params['temperature'] = $temperature;
        }

        if ($responseFormat !== null && $this->supportsJsonResponse($model)) {
            $params['response_format'] = $responseFormat;
        }

        // Enable search tools for models that support it
        if ($this->supportsSearch($model)) {
            $params['tools'] = [
                [
                    'type' => 'search_web',
                ],
            ];
            // Auto invocation for the tool
            $params['tool_choice'] = 'auto';
        }

        try {
            $response = $this->client->chat()->create($params);
            return $response->choices[0]->message->content;
        } catch (\Exception $e) {
            throw new Exception('OpenAI API error: ' . $e->getMessage());
        }
    }

    /**
     * Get the available models for this provider
     *
     * @return array List of available model identifiers
     */
    public function getAvailableModels(): array {
        return [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4-turbo',
            'gpt-4',
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-instruct',
            'gpt-4-vision-preview',
            'gpt-4o-search-preview',
        ];
    }

    /**
     * Check if a model supports image input
     *
     * @param string $model The model to check
     * @return bool True if the model supports image input
     */
    public function supportsImages(string $model): bool {
        return in_array($model, $this->imageModels);
    }

    /**
     * Check if a model supports JSON response format
     *
     * @param string $model The model to check
     * @return bool True if the model supports JSON response format
     */
    public function supportsJsonResponse(string $model): bool {
        return in_array($model, $this->jsonModels);
    }

    /**
     * Check if a model supports web search capability
     *
     * @param string $model The model to check
     * @return bool True if the model supports web search
     */
    public function supportsSearch(string $model): bool {
        return in_array($model, $this->searchModels);
    }
}
