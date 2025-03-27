<?php

namespace App\Handlers\Prompt;

use App\Handlers\PromptHandlerInterface;
use App\Libraries\LLMService\LLMService;
use App\Libraries\ElasticSearch\ElasticSearchService;
use Exception;

class IndividualPropertyHandler implements PromptHandlerInterface {
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
     * Handle individual property search functionality
     *
     * @param string $prompt The original prompt
     * @return array The action result
     */
    public function handle(string $prompt): array {
        // Configure the service with lenient search settings
        $this->esService->setLimit(1)->setScoreThreshold(0.3);

        // Use directly lenient search settings
        $searchOptions = [
            'fuzziness' => 'AUTO:1,4', // Maximum fuzziness from the start
            'prefix_length' => 1, // Very small prefix requirement
            'minimum_should_match' => '20%', // Low match requirement
            'slop' => 3, // High slop for phrase matching
        ];

        // Perform the search with lenient settings
        $searchResults = $this->esService->autocompleteProperty($prompt, null, $searchOptions);

        // Generate a fun message about the search results
        //$aiMessage = $this->generatePropertySearchMessage($prompt, $searchResults);

        return [
            'action_type' => 'property_lookup',
            'details' => 'Looking up specific property details',
            'address' => [
                'original' => $prompt,
            ],
            'search_results' => $searchResults,
            //'ai_message' => $aiMessage,
        ];
    }

    /**
     * Generate a fun, personalized message about property search results
     *
     * @param string $prompt The original search prompt
     * @param array $searchResults The search results from ElasticSearch
     * @return string A fun message with personality
     */
    private function generatePropertySearchMessage(string $prompt, array $searchResults): string {
        // Check if we found any results - based on actual structure
        $foundProperty = !empty($searchResults) && is_array($searchResults);

        // Get confidence score and address if available
        $confidence = 0;
        $propertyAddress = '';
        if ($foundProperty && count($searchResults) > 0) {
            $topHit = $searchResults[0];
            $confidence = $topHit['score'] ?? 0;
            $propertyAddress = $topHit['text'] ?? 'this property';
        }

        // Normalize confidence to 0-100%
        $confidencePercent = min(round(($confidence / 10000) * 100), 100);

        try {
            // Set up system prompt for the AI
            $systemPrompt = 'You are a helpful AI assistant. You help users find properties.';
            $this->llmService->setSystemPrompt($systemPrompt);
            $this->llmService->setModel('gpt-4o-mini');
            $this->llmService->setTemperature(0.7); // More creative
            $this->llmService->setMaxTokens(100); // Keep it concise

            // Create user message with search context
            $userMessage = '';
            if ($foundProperty) {
                $userMessage = "You searched for a property based on my query: \"$prompt\". You found a property at \"$propertyAddress\". Generate a short message (1 sentences) about this result.";
            } else {
                $userMessage = "You searched for a property based on my query: \"$prompt\", but you couldn't find any matches. Generate a short message (1 sentence) about not finding any results. Make it slightly apologetic but encouraging to try again with a more specific query. Be brief!";
            }

            // Use the ask method which handles the conversation
            $aiMessage = $this->llmService->ask($userMessage);

            if (!empty($aiMessage)) {
                return trim($aiMessage);
            }
        } catch (Exception $e) {
            // Log the error but don't expose it
            log_message('error', 'Error generating AI message: ' . $e->getMessage());
        }

        // Return a fallback message if anything fails
        if ($foundProperty) {
            return "Found $propertyAddress with $confidencePercent% confidence!";
        } else {
            return "Hmm, couldn't find that property. Could you be more specific?";
        }
    }
}
