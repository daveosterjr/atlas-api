<?php

namespace App\Libraries\LLM;

use App\Config\LLM as LLMConfig;
use App\Libraries\LLM\Providers\OpenAIProvider;
use Exception;
use InvalidArgumentException;

/**
 * LLMService - A wrapper for Large Language Model operations
 *
 * This class provides a unified interface for interacting with various LLM providers.
 */
class LLMService {
    /**
     * @var LLMConfig The configuration
     */
    protected $config;

    /**
     * @var LLMProviderInterface The provider implementation
     */
    protected $provider;

    /**
     * @var array Available provider implementations
     */
    protected $providers = [
        'openai' => OpenAIProvider::class,
    ];

    /**
     * Constructor
     */
    public function __construct($config = null) {
        // Handle the case where BaseService passes false as config
        if (!($config instanceof LLMConfig) && $config !== null) {
            $config = null;
        }

        $this->config = $config ?? config('LLM');
        $this->initializeProvider();
    }

    /**
     * Initialize the provider based on configuration
     */
    protected function initializeProvider(): void {
        $providerName = $this->config->provider;

        if (!isset($this->providers[$providerName])) {
            throw new InvalidArgumentException("Unsupported LLM provider: {$providerName}");
        }

        $providerClass = $this->providers[$providerName];
        $this->provider = new $providerClass($this->config);
    }

    /**
     * Set a different provider
     *
     * @param string $provider Provider name
     * @return self
     */
    public function setProvider(string $provider): self {
        $this->config->provider = $provider;
        $this->initializeProvider();
        return $this;
    }

    /**
     * Set a different model
     *
     * @param string $model Model name
     * @return self
     */
    public function setModel(string $model): self {
        $this->config->defaultModel = $model;
        return $this;
    }

    /**
     * Set the temperature
     *
     * @param float $temperature Temperature value (0-1)
     * @return self
     */
    public function setTemperature(float $temperature): self {
        if ($temperature < 0 || $temperature > 1) {
            throw new InvalidArgumentException('Temperature must be between 0 and 1');
        }

        $this->config->temperature = $temperature;
        return $this;
    }

    /**
     * Get the current configuration
     *
     * @return LLMConfig
     */
    public function getConfig(): LLMConfig {
        return $this->config;
    }

    /**
     * Perform a chat completion
     *
     * @param array $messages The messages in the conversation
     * @param array $options Additional options for the request
     * @return array The response from the LLM
     */
    public function chatCompletion(array $messages, array $options = []): array {
        return $this->provider->chatCompletion($messages, $options);
    }

    /**
     * Perform a text completion
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options for the request
     * @return array The response from the LLM
     */
    public function textCompletion(string $prompt, array $options = []): array {
        return $this->provider->textCompletion($prompt, $options);
    }

    /**
     * Generate embeddings for a text
     *
     * @param string $text The text to generate embeddings for
     * @return array The embeddings
     */
    public function generateEmbeddings(string $text): array {
        return $this->provider->generateEmbeddings($text);
    }

    /**
     * Helper method to extract just the content from a chat completion response
     *
     * @param array $messages The messages in the conversation
     * @param array $options Additional options for the request
     * @return string The response content
     */
    public function getChatContent(array $messages, array $options = []): string {
        $response = $this->chatCompletion($messages, $options);
        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Helper method to extract just the content from a text completion response
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options for the request
     * @return string The response content
     */
    public function getTextContent(string $prompt, array $options = []): string {
        $response = $this->textCompletion($prompt, $options);
        return $response['choices'][0]['message']['content'] ?? '';
    }
}
