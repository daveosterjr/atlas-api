<?php

namespace App\Handlers\Prompt;

use App\Handlers\PromptHandlerInterface;
use App\Libraries\LLMService\LLMService;
use App\Libraries\ElasticSearch\ElasticSearchService;

class IndividualPersonHandler implements PromptHandlerInterface {
    /**
     * @var LLMService
     */
    protected $llmService;

    /**
     * @var ElasticSearchService
     */
    protected $esService;

    /**
     * Constructor
     *
     * @param LLMService $llmService
     * @param ElasticSearchService $esService
     */
    public function __construct(LLMService $llmService, ElasticSearchService $esService) {
        $this->llmService = $llmService;
        $this->esService = $esService;
    }

    /**
     * Handle individual person search
     *
     * @param string $prompt The original prompt
     * @return array The action result
     */
    public function handle(string $prompt): array {
        // TODO: Implement individual person search logic

        return [
            'action_type' => 'person_profile_search',
            'details' => 'Searching for individual person profile',
            'query' => $prompt,
        ];
    }
}
