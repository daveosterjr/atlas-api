<?php

namespace App\Libraries\LLMService;

use App\Libraries\LLMService\Providers\OpenAIProvider;
use Exception;
use InvalidArgumentException;

/**
 * LLMService - A flexible wrapper for LLM services
 *
 * This class provides a unified interface for interacting with various LLM providers,
 * starting with OpenAI but designed to be extensible to other providers.
 */
class LLMService {
    /**
     * @var string The API key for the LLM provider
     */
    private string $apiKey;

    /**
     * @var string The LLM provider (default: 'openai')
     */
    private string $provider;

    /**
     * @var string The default model to use
     */
    private string $defaultModel;

    /**
     * @var array Provider implementations
     */
    private array $providers = [];

    /**
     * @var array Conversation history
     */
    private array $conversationHistory = [];

    /**
     * @var string|null The system prompt to use
     */
    private ?string $systemPrompt = null;

    /**
     * @var float The temperature parameter (0-2)
     */
    private float $temperature = 1.0;

    /**
     * @var int The maximum number of tokens to generate
     */
    private int $maxTokens = 1000;

    /**
     * Constructor
     *
     * @param string $apiKey The API key for the LLM provider
     * @param string $provider The LLM provider (default: 'openai')
     * @param string $defaultModel The default model to use (default: 'gpt-4o-mini')
     */
    public function __construct(
        string $apiKey,
        string $provider = 'openai',
        string $defaultModel = 'gpt-4o-mini',
    ) {
        $this->apiKey = $apiKey;
        $this->provider = $provider;
        $this->defaultModel = $defaultModel;
        $this->initializeProviders();
    }

    /**
     * Initialize the providers
     */
    private function initializeProviders(): void {
        // Register providers
        $this->providers['openai'] = new OpenAIProvider();

        // Initialize the selected provider
        if (isset($this->providers[$this->provider])) {
            $this->providers[$this->provider]->initialize($this->apiKey);
        } else {
            throw new InvalidArgumentException("Unsupported provider: {$this->provider}");
        }
    }

    /**
     * Set the current provider
     *
     * @param string $provider The provider name
     * @return self
     */
    public function setProvider(string $provider): self {
        if (!isset($this->providers[$provider])) {
            throw new InvalidArgumentException("Unsupported provider: {$provider}");
        }

        $this->provider = $provider;
        $this->providers[$provider]->initialize($this->apiKey);

        return $this;
    }

    /**
     * Get available models for the current provider
     *
     * @return array List of available model identifiers
     */
    public function getAvailableModels(): array {
        return $this->providers[$this->provider]->getAvailableModels();
    }

    /**
     * Set the system prompt
     *
     * @param string|null $prompt The system prompt text
     * @return self
     */
    public function setSystemPrompt(?string $prompt): self {
        $this->systemPrompt = $prompt;
        return $this;
    }

    /**
     * Set the model to use
     *
     * @param string $model The model to use
     * @return self
     */
    public function setModel(string $model): self {
        $this->defaultModel = $model;
        return $this;
    }

    /**
     * Set the temperature parameter
     *
     * @param float $temperature The temperature (0-2)
     * @return self
     */
    public function setTemperature(float $temperature): self {
        if ($temperature < 0 || $temperature > 2) {
            throw new InvalidArgumentException('Temperature must be between 0 and 2');
        }
        $this->temperature = $temperature;
        return $this;
    }

    /**
     * Set the maximum number of tokens to generate
     *
     * @param int $maxTokens The maximum number of tokens
     * @return self
     */
    public function setMaxTokens(int $maxTokens): self {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    /**
     * Clear the conversation history
     *
     * @return self
     */
    public function clearConversation(): self {
        $this->conversationHistory = [];
        return $this;
    }

    /**
     * Add a message to the conversation history
     *
     * @param string $role The role ('user', 'assistant', or 'system')
     * @param string $content The message content
     * @param array|null $imageUrls Optional array of image URLs to attach
     * @return self
     */
    public function addMessage(string $role, string $content, ?array $imageUrls = null): self {
        $message = ['role' => $role, 'content' => []];

        // Add text content
        $message['content'][] = [
            'type' => 'text',
            'text' => $content,
        ];

        // Add images if provided
        if (!empty($imageUrls)) {
            // Check if current model supports images
            $model = $this->defaultModel;
            if (!$this->providers[$this->provider]->supportsImages($model)) {
                throw new Exception("Model {$model} does not support image input");
            }

            foreach ($imageUrls as $imageUrl) {
                $message['content'][] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $imageUrl,
                    ],
                ];
            }
        }

        $this->conversationHistory[] = $message;
        return $this;
    }

    /**
     * Ask a question and get a response
     *
     * @param string $question The question to ask
     * @param array|null $imageUrls Optional array of image URLs to attach
     * @param string|null $model Override the default model
     * @return string The response text
     */
    public function ask(string $question, ?array $imageUrls = null, ?string $model = null): string {
        // Add the user question to the conversation
        $this->addMessage('user', $question, $imageUrls);

        // Get the response
        $response = $this->sendRequest($model);

        // Add the assistant's response to the conversation
        $this->addMessage('assistant', $response);

        return $response;
    }

    /**
     * Ask a question and get a JSON response
     *
     * @param string $question The question to ask
     * @param array $schema The JSON schema that defines the response structure
     * @param array|null $imageUrls Optional array of image URLs to attach
     * @param string|null $model Override the default model
     * @return array The JSON response as an associative array
     */
    public function askForJson(
        string $question,
        array $schema,
        ?array $imageUrls = null,
        ?string $model = null,
    ): array {
        $model = $model ?? $this->defaultModel;

        // Check if model supports JSON response
        if (!$this->providers[$this->provider]->supportsJsonResponse($model)) {
            throw new Exception("Model {$model} does not support JSON response format");
        }

        $prompt =
            $question .
            "\n\nRespond with a valid JSON object matching this schema: " .
            json_encode($schema);

        // Add the user question to the conversation
        $this->addMessage('user', $prompt, $imageUrls);

        // Get the response with JSON formatting
        $jsonResponse = $this->providers[$this->provider]->complete(
            $this->prepareMessages(),
            $model,
            $this->temperature,
            $this->maxTokens,
            ['type' => 'json_object'],
        );

        // Add the assistant's response to the conversation
        $this->addMessage('assistant', $jsonResponse);

        return json_decode($jsonResponse, true);
    }

    /**
     * Ask a yes/no question and get a boolean response
     *
     * @param string $question The question to ask
     * @param array|null $imageUrls Optional array of image URLs to attach
     * @param string|null $model Override the default model
     * @return bool True for yes, false for no
     */
    public function askYesNo(
        string $question,
        ?array $imageUrls = null,
        ?string $model = null,
    ): bool {
        $prompt = $question . "\n\nRespond with ONLY 'true' or 'false'.";

        // Add the user question to the conversation
        $this->addMessage('user', $prompt, $imageUrls);

        // Get the response
        $response = $this->sendRequest($model);

        // Add the assistant's response to the conversation
        $this->addMessage('assistant', $response);

        // Parse the response to boolean
        $normalizedResponse = strtolower(trim($response));
        return $normalizedResponse === 'true' || $normalizedResponse === 'yes';
    }

    /**
     * Prepare the messages array for the API request
     *
     * @return array The messages array
     */
    private function prepareMessages(): array {
        $messages = [];

        // Add system prompt if set
        if ($this->systemPrompt !== null) {
            $messages[] = [
                'role' => 'system',
                'content' => $this->systemPrompt,
            ];
        }

        // Add conversation history
        foreach ($this->conversationHistory as $message) {
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * Send the request to the LLM provider
     *
     * @param string|null $model Override the default model
     * @param array|null $responseFormat The format of the response
     * @return string The response text
     */
    private function sendRequest(?string $model = null, ?array $responseFormat = null): string {
        $model = $model ?? $this->defaultModel;

        // Check if model supports search and handle special parameters
        if ($this->providers[$this->provider]->supportsSearch($model)) {
            // Search models don't support temperature, max_tokens and other sampling parameters
            return $this->providers[$this->provider]->complete(
                $this->prepareMessages(),
                $model,
                0.0, // Temperature value doesn't matter for search models
                $this->maxTokens,
                $responseFormat,
            );
        }

        return $this->providers[$this->provider]->complete(
            $this->prepareMessages(),
            $model,
            $this->temperature,
            $this->maxTokens,
            $responseFormat,
        );
    }

    /**
     * Ask a question using a model with web search capability
     *
     * @param string $question The question to ask
     * @param string|null $model Override the default search model (default: 'gpt-4o-search-preview')
     * @param array|null $imageUrls Optional array of image URLs to attach
     * @return string The response text
     */
    public function askWithSearch(
        string $question,
        ?string $model = 'gpt-4o-search-preview',
        ?array $imageUrls = null,
    ): string {
        // Verify the model supports search
        $searchModel = $model ?? 'gpt-4o-search-preview';
        if (!$this->providers[$this->provider]->supportsSearch($searchModel)) {
            throw new Exception("Model {$searchModel} does not support web search");
        }

        // Add the user question to the conversation
        $this->addMessage('user', $question, $imageUrls);

        // Get the response
        $response = $this->sendRequest($searchModel);

        // Add the assistant's response to the conversation
        $this->addMessage('assistant', $response);

        return $response;
    }

    /**
     * Check if a model supports web search
     *
     * @param string $model The model to check
     * @return bool True if the model supports web search
     */
    public function modelSupportsSearch(string $model): bool {
        return $this->providers[$this->provider]->supportsSearch($model);
    }

    /**
     * Ask a question using a search-capable model and get a JSON response
     *
     * @param string $question The question to ask
     * @param array $schema The JSON schema that defines the response structure
     * @param string|null $model Override the default search model (default: 'gpt-4o-search-preview')
     * @param array|null $imageUrls Optional array of image URLs to attach
     * @return array The JSON response as an associative array
     */
    public function askForJsonWithSearch(
        string $question,
        array $schema,
        ?string $model = 'gpt-4o-search-preview',
        ?array $imageUrls = null,
    ): array {
        // Verify the model supports search
        $searchModel = $model ?? 'gpt-4o-search-preview';
        if (!$this->providers[$this->provider]->supportsSearch($searchModel)) {
            throw new Exception("Model {$searchModel} does not support web search");
        }

        // Check if model supports JSON response
        if (!$this->providers[$this->provider]->supportsJsonResponse($searchModel)) {
            throw new Exception("Model {$searchModel} does not support JSON response format");
        }

        $prompt =
            $question .
            "\n\nRespond with a valid JSON object matching this schema: " .
            json_encode($schema);

        // Add the user question to the conversation
        $this->addMessage('user', $prompt, $imageUrls);

        // Get the response with JSON formatting
        $jsonResponse = $this->providers[$this->provider]->complete(
            $this->prepareMessages(),
            $searchModel,
            null,
            $this->maxTokens,
            ['type' => 'json_object'],
        );

        // Add the assistant's response to the conversation
        $this->addMessage('assistant', $jsonResponse);

        return json_decode($jsonResponse, true);
    }
}
