<?php

namespace App\Libraries\LLMService;

/**
 * Interface for LLM Provider implementations
 */
interface LLMProviderInterface
{
    /**
     * Initialize the client with the API key
     * 
     * @param string $apiKey The API key for the LLM provider
     * @return void
     */
    public function initialize(string $apiKey): void;
    
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
    public function complete(array $messages, string $model, float $temperature, int $maxTokens, ?array $responseFormat = null): string;
    
    /**
     * Get the available models for this provider
     * 
     * @return array List of available model identifiers
     */
    public function getAvailableModels(): array;
    
    /**
     * Check if a model supports image input
     * 
     * @param string $model The model to check
     * @return bool True if the model supports image input
     */
    public function supportsImages(string $model): bool;
    
    /**
     * Check if a model supports JSON response format
     * 
     * @param string $model The model to check
     * @return bool True if the model supports JSON response format
     */
    public function supportsJsonResponse(string $model): bool;
    
    /**
     * Check if a model supports web search capability
     * 
     * @param string $model The model to check
     * @return bool True if the model supports web search
     */
    public function supportsSearch(string $model): bool;
} 