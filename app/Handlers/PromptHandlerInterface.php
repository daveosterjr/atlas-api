<?php

namespace App\Handlers;

interface PromptHandlerInterface {
    /**
     * Handle a user prompt
     *
     * @param string $prompt The user's search prompt
     * @return array Response with action details
     */
    public function handle(string $prompt): array;
}
