<?php

namespace App\Handlers\Prompt;

use App\Handlers\PromptHandlerInterface;

class DefaultHandler implements PromptHandlerInterface {
    /**
     * Handle default/fallback prompt scenarios
     *
     * @param string $prompt The original prompt
     * @return array The action result
     */
    public function handle(string $prompt): array {
        return [
            'action_type' => 'general_search',
            'details' => 'Performing general search with provided criteria',
            'query' => $prompt,
        ];
    }
}
