<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Libraries\LLMService\LLMService;
use App\Libraries\ElasticSearch\ElasticSearchService;
use App\Config\LLM;
use App\Config\ElasticSearch;
use App\Handlers\Prompt\DefaultHandler;
use App\Handlers\Prompt\IndividualPropertyHandler;
use App\Handlers\Prompt\IndividualPersonHandler;
use App\Handlers\Prompt\IndividualCompanyHandler;
use App\Handlers\Prompt\MultiplePropertiesHandler;
use App\Handlers\Prompt\MultiplePeopleHandler;
use App\Handlers\Prompt\MultipleCompaniesHandler;
use Exception;

class Prompt extends ResourceController {
    protected $format = 'json';
    private $llmService;
    private $esService;
    private $handlers = [];

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

        // Initialize handlers
        $this->initHandlers();
    }

    /**
     * Initialize all the prompt handlers
     */
    private function initHandlers() {
        $this->handlers = [
            'individual_person' => new IndividualPersonHandler($this->llmService, $this->esService),
            'individual_property' => new IndividualPropertyHandler(
                $this->llmService,
                $this->esService,
            ),
            'individual_company' => new IndividualCompanyHandler(
                $this->llmService,
                $this->esService,
            ),
            'multiple_properties' => new MultiplePropertiesHandler(
                $this->llmService,
                $this->esService,
            ),
            'multiple_people' => new MultiplePeopleHandler($this->llmService, $this->esService),
            'multiple_companies' => new MultipleCompaniesHandler(
                $this->llmService,
                $this->esService,
            ),
        ];
    }

    public function index() {
        // Handle both POST and GET requests for flexibility
        if ($this->request->getMethod() === 'POST') {
            // Get JSON data from POST request
            $data = $this->request->getJSON(true);
            $prompt = $data['prompt'] ?? '';
            $applied_filters = $data['filters'] ?? [];
        } else {
            // Fallback to GET parameter
            $prompt = $this->request->getGet('prompt') ?? '';
            $applied_filters = $this->request->getGet('filters') ?? [];
        }

        // Handle case where there are filters but no prompt text
        if (empty($prompt) && !empty($applied_filters)) {
            return $this->handleEmptyPromptWithFilters($applied_filters);
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

        if (!empty($applied_filters)) {
            // Check if the first filter is a source_type filter
            $filter = $applied_filters[0];
            if (isset($filter['type']) && $filter['type'] === 'source_type') {
                $category = null;

                // Map filter values to categories
                if ($filter['value'] === 'properties') {
                    $category = 'multiple_properties';
                } elseif ($filter['value'] === 'contacts') {
                    $category = 'multiple_people';
                }

                // If we have a valid category, process with the corresponding handler
                if ($category && isset($this->handlers[$category])) {
                    $handler = $this->handlers[$category];
                    // Pass the applied_filters to the handler
                    $actionTaken = $handler->handle($prompt, $applied_filters);

                    return $this->respond([
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Prompt processed with filter override',
                        'data' => [
                            'categorization' => [
                                'category' => $category,
                                'confidence' => 1.0,
                                'explanation' => 'Category determined by filter',
                            ],
                            'action' => $actionTaken,
                        ],
                        'meta' => [
                            'timestamp' => time(),
                            'version' => '1.0',
                        ],
                    ]);
                }
            }
        }

        try {
            // Add debug info
            $config = new LLM();

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

            // Get the appropriate handler for this category
            $category = $result['category'];
            $handler = $this->handlers[$category] ?? new DefaultHandler();

            // Use the handler to process the prompt
            $actionTaken = $handler->handle($prompt, $applied_filters);

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
     * Handle requests with empty prompt but applied filters
     * Just names the filters without extracting additional criteria
     *
     * @param array $appliedFilters List of applied filters
     * @return mixed API response
     */
    private function handleEmptyPromptWithFilters(array $appliedFilters): mixed {
        // Determine category based on filters
        $category = 'properties'; // Default

        // Check filter source_type to determine category
        foreach ($appliedFilters as $filter) {
            if (isset($filter['source_type'])) {
                if ($filter['source_type'] === 'contacts') {
                    $category = 'contacts';

                    break;
                } elseif ($filter['source_type'] === 'companies') {
                    $category = 'companies';
                    break;
                } elseif ($filter['source_type'] === 'properties') {
                    $category = 'properties';
                    break;
                }
            }
        }

        // Generate a search name based on filters
        $searchName = $this->generateSearchNameFromFilters($appliedFilters, $category);

        // Generate a random search ID
        $searchId = 'search_' . uniqid();

        // Create the action response
        $actionData = [
            'action_type' =>
                $category === 'properties'
                    ? 'get_properties_filters'
                    : ($category === 'contacts'
                        ? 'people_search'
                        : 'companies_search'),
            'details' => 'Filter-based search without prompt text',
            'query' => '',
            'saved_search' => [
                'id' => $searchId,
                'name' => $searchName,
                'filters' => $appliedFilters,
                'criteria' => [
                    'search_type' => $category,
                    'search_id' => $searchId,
                    'search_name' => $searchName,
                    'extracted_criteria' => $appliedFilters,
                    'filter_count' => count($appliedFilters),
                    'applied_filters_count' => count($appliedFilters),
                ],
            ],
        ];

        return $this->respond([
            'status' => 'success',
            'code' => 200,
            'message' => 'Filters processed without prompt text',
            'data' => [
                'categorization' => [
                    'category' => $category,
                    'confidence' => 1.0,
                    'explanation' => 'Category determined by filter source_type',
                ],
                'action' => $actionData,
            ],
            'meta' => [
                'timestamp' => time(),
                'version' => '1.0',
            ],
        ]);
    }

    /**
     * Generate a search name based on applied filters
     *
     * @param array $filters Applied filters
     * @param string $category Search category (multiple_properties, multiple_people, etc.)
     * @return string Generated search name
     */
    private function generateSearchNameFromFilters(array $filters, string $category): string {
        if (empty($filters)) {
            return 'Untitled Search';
        }

        // Use LLM service to generate a search name based on filters
        try {
            // Set model to GPT-4o
            $this->llmService->setModel('gpt-4o-mini');
            $this->llmService->setTemperature(0.7);

            // Create a system prompt for generating search names
            $systemPrompt = <<<EOT
            You are a helpful assistant that generates concise, descriptive names for saved searches.
            Create a search name that summarizes the key filters while remaining brief.
            For property searches: Focus on property type, location, bedrooms/bathrooms, and price when available.
            For contact searches: Focus on profession, location, age, and income when available.
            For company searches: Focus on industry, location, size, and revenue when available.
            Keep names under 60 characters when possible.
            IMPORTANT: Do not include any quotation marks around the name in your response.
            EOT;

            $this->llmService->setSystemPrompt($systemPrompt);

            // Define JSON schema for the response
            $schema = [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' =>
                            'A concise, descriptive name for the search without any quotation marks',
                    ],
                ],
                'required' => ['name'],
            ];

            // Create a prompt with the filters and category
            $prompt =
                "Generate a concise, descriptive name for a $category search with these filters:\n" .
                json_encode($filters, JSON_PRETTY_PRINT);

            // Ask the LLM for a name using JSON format
            $response = $this->llmService->askForJson($prompt, $schema);

            // Extract the generated name from the JSON response
            $generatedName = isset($response['name']) ? trim($response['name']) : '';
            $generatedName = str_replace(['"', "'"], '', $generatedName);

            // Fallback if no name was generated
            if (empty($generatedName)) {
                throw new Exception('Failed to generate search name');
            }

            return $generatedName;
        } catch (Exception $e) {
            // Fallback names if LLM fails
            $fallbackNames = [
                'multiple_properties' => 'Property Search',
                'multiple_people' => 'Contact Search',
                'multiple_companies' => 'Company Search',
                'individual_property' => 'Property Lookup',
                'individual_person' => 'Contact Lookup',
                'individual_company' => 'Company Lookup',
                'other' => 'Custom Search',
            ];

            $baseName = $fallbackNames[$category] ?? 'Search';
            return $baseName . ' (' . count($filters) . ' filters)';
        }
    }
}
