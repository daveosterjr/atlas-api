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
            $actionTaken = $handler->handle($prompt);

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
}
