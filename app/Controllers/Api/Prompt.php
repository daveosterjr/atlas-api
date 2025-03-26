<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Libraries\LLMService\LLMService;
use App\Libraries\ElasticSearch\ElasticSearchService;
use App\Config\LLM;
use App\Config\ElasticSearch;
use Exception;

class Prompt extends ResourceController {
    protected $format = 'json';
    private $llmService;
    private $esService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger,
    ) {
        parent::initController($request, $response, $logger);

        // Create LLM service with API key from config
        $llmConfig = new LLM();
        // Make sure we pass a string API key
        $apiKey = $llmConfig->apiKey;

        $this->llmService = new LLMService($apiKey);

        // Create ElasticSearch service
        $this->esService = new ElasticSearchService(new ElasticSearch());
    }

    public function index() {
        // Handle both POST and GET requests for flexibility
        if ($this->request->getMethod() === 'POST') {
            // Get JSON data from POST request
            $data = $this->request->getJSON(true);
            $prompt = $data['prompt'] ?? '';
        } else {
            // Fallback to GET parameter
            $prompt = $this->request->getGet('prompt') ?? '';
        }

        if (empty($prompt)) {
            return $this->respond(
                [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'No prompt provided',
                    'data' => [],
                ],
                400,
            );
        }

        try {
            // Add debug info
            $config = new LLM();
            log_message('debug', 'API key set: ' . (!empty($config->apiKey) ? 'Yes' : 'No'));
            log_message('debug', 'LLM Provider: ' . $config->provider);
            log_message('debug', 'Default Model: ' . $config->defaultModel);

            // Explicitly set model to GPT-4o
            $this->llmService->setModel('gpt-4o');

            // Define the JSON schema for categorizing the prompt
            $schema = [
                'type' => 'object',
                'properties' => [
                    'category' => [
                        'type' => 'string',
                        'enum' => [
                            'individual_person',
                            'individual_property',
                            'individual_company',
                            'multiple_properties',
                            'multiple_people',
                            'multiple_companies',
                            'other',
                        ],
                    ],
                    'confidence' => [
                        'type' => 'number',
                        'minimum' => 0,
                        'maximum' => 1,
                    ],
                    'explanation' => [
                        'type' => 'string',
                    ],
                ],
            ];

            // Set system prompt for categorization task
            $systemPrompt = <<<EOT
            You are an AI that categorizes search prompts based on their intent. Determine whether the user is searching for:
            - individual_person
            - individual_property
            - individual_company
            - multiple_properties
            - multiple_people
            - multiple_companies
            - other

            Priority rules for mixed queries:
            1. Properties + People → multiple_properties
            2. Properties + Companies → multiple_properties
            3. People + Companies (no properties) → multiple_people

            Examples:
            - '65 and older absentee owned' → multiple_properties (properties owned by elderly absentee owners)
            - 'Vacant properties owned by corporations' → multiple_properties
            - 'Small business owners in Phoenix' → multiple_people
            - 'Rental property management companies' → multiple_companies

            Be very careful to identify the primary entity being searched for: properties, people, or companies.
            EOT;

            // Configure LLM parameters
            $this->llmService->setTemperature(0.3);

            // Replace chatCompletion with appropriate method
            $this->llmService->setSystemPrompt($systemPrompt);
            $content = $this->llmService->askForJson(
                "Categorize the following prompt into one of these categories: individual_person, individual_property, individual_company, multiple_properties, multiple_people, multiple_companies, or other. Prompt: \"$prompt\"",
                $schema,
            );

            // Result is already decoded JSON
            $result = $content;

            if (!$result || !isset($result['category'])) {
                throw new Exception('Failed to parse category from LLM response');
            }

            // Define action based on category
            $actionTaken = [];
            switch ($result['category']) {
                case 'individual_person':
                    // Handle individual person search
                    $actionTaken = [
                        'action_type' => 'person_profile_search',
                        'details' => 'Searching for individual person profile',
                    ];
                    break;

                case 'individual_property':
                    // Handle individual property search - complete address using LLM
                    $actionTaken = $this->handlePropertySearch($prompt);
                    break;

                case 'individual_company':
                    // Handle individual company search
                    $actionTaken = [
                        'action_type' => 'company_profile_search',
                        'details' => 'Searching for company information',
                    ];
                    break;

                case 'multiple_properties':
                    // Handle multiple properties search
                    $actionTaken = [
                        'action_type' => 'property_list_filter',
                        'details' => 'Filtering property listings based on criteria',
                    ];
                    break;

                case 'multiple_people':
                    // Handle multiple people search
                    $actionTaken = [
                        'action_type' => 'people_search',
                        'details' => 'Searching for multiple individuals',
                    ];
                    break;

                case 'multiple_companies':
                    // Handle multiple companies search
                    $actionTaken = [
                        'action_type' => 'company_list_filter',
                        'details' => 'Filtering company listings based on criteria',
                    ];
                    break;

                case 'other':
                default:
                    // Handle other search types
                    $actionTaken = [
                        'action_type' => 'general_search',
                        'details' => 'Performing general search with provided criteria',
                    ];
                    break;
            }

            // Create standard API response structure
            $response = [
                'status' => 'success',
                'code' => 200,
                'message' => 'Prompt categorized successfully',
                'data' => [
                    'categorization' => $result,
                    'action' => $actionTaken,
                ],
                'meta' => [
                    'timestamp' => time(),
                    'version' => '1.0',
                ],
            ];

            return $this->respond($response);
        } catch (Exception $e) {
            $errorDetails = [
                'message' => $e->getMessage(),
            ];

            // Don't include detailed error info in production
            if (ENVIRONMENT !== 'production') {
                $errorDetails['file'] = $e->getFile();
                $errorDetails['line'] = $e->getLine();
                $errorDetails['trace'] = $e->getTraceAsString();
            }

            // Create error response
            $response = [
                'status' => 'error',
                'code' => 500,
                'message' => 'Error processing prompt',
                'data' => [],
                'meta' => [
                    'timestamp' => time(),
                    'version' => '1.0',
                ],
            ];

            if (ENVIRONMENT !== 'production') {
                $response['debug'] = [
                    'error_details' => $errorDetails,
                ];
            }

            return $this->respond($response, 500);
        }
    }

    /**
     * Handle property search functionality
     *
     * @param string $prompt The original prompt
     * @return array The action result
     */
    private function handlePropertySearch(string $prompt): array {
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
        $aiMessage = $this->generatePropertySearchMessage($prompt, $searchResults);

        return [
            'action_type' => 'property_lookup',
            'details' => 'Looking up specific property details',
            'address' => [
                'original' => $prompt,
            ],
            'search_results' => $searchResults,
            'ai_message' => $aiMessage,
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

    public function options() {
        $this->response->setHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $this->response->setHeader(
            'Access-Control-Allow-Headers',
            'Origin, X-Requested-With, Content-Type, Accept, Authorization, Access-Control-Request-Method, Access-Control-Request-Headers',
        );
        $this->response->setHeader(
            'Access-Control-Allow-Methods',
            'GET, POST, OPTIONS, PUT, DELETE, PATCH',
        );
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
        $this->response->setHeader('Access-Control-Max-Age', '7200');
        return $this->response->setStatusCode(200);
    }
}
