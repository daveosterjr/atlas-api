<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class LLM extends BaseConfig {
    /**
     * API Key for the LLM provider
     */
    public string $apiKey = '';

    /**
     * LLM provider (e.g., 'openai', 'anthropic', etc.)
     */
    public string $provider = 'openai';

    /**
     * Default model to use
     */
    public string $defaultModel = 'gpt-4o-mini';

    /**
     * Default temperature setting (0-1)
     */
    public float $temperature = 0.7;

    /**
     * Maximum tokens to generate in a completion
     */
    public int $maxTokens = 1024;

    public function __construct() {
        parent::__construct();

        // Load from environment variables if available
        $this->apiKey = getenv('LLM_API_KEY') ?: $this->apiKey;

        // Optional environment overrides
        if (getenv('LLM_PROVIDER')) {
            $this->provider = getenv('LLM_PROVIDER');
        }

        if (getenv('LLM_DEFAULT_MODEL')) {
            $this->defaultModel = getenv('LLM_DEFAULT_MODEL');
        }
    }
}
