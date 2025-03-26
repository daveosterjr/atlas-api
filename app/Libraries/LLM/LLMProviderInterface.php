<?php

namespace App\Libraries\LLM;

interface LLMProviderInterface {
    /**
     * Perform a chat completion
     *
     * @param array $messages The messages in the conversation
     * @param array $options Additional options for the request
     * @return array The response from the LLM
     */
    public function chatCompletion(array $messages, array $options = []): array;

    /**
     * Perform a text completion
     *
     * @param string $prompt The text prompt
     * @param array $options Additional options for the request
     * @return array The response from the LLM
     */
    public function textCompletion(string $prompt, array $options = []): array;

    /**
     * Generate embeddings for a text
     *
     * @param string $text The text to generate embeddings for
     * @return array The embeddings
     */
    public function generateEmbeddings(string $text): array;
}
