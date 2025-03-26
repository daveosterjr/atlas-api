<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Libraries\ElasticSearch\ElasticSearchService;
use App\Config\ElasticSearch;

class Autocomplete extends ResourceController {
    protected $format = 'json';
    private $esService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger,
    ) {
        parent::initController($request, $response, $logger);

        // Create a direct instance of the service with a new config
        $this->esService = new ElasticSearchService(new ElasticSearch());
    }

    public function index() {
        // Get the search query from the request
        $query = $this->request->getGet('query') ?? '';

        try {
            // For debugging: display the ES host configuration
            $config = $this->esService->getConfig();
            log_message('debug', 'ElasticSearch config host: ' . $config->host);

            // Test the connection
            if (!$this->esService->testConnection()) {
                return $this->failServerError('Failed to connect to Elasticsearch');
            }

            // Configure the service
            $this->esService->setLimit(10)->setScoreThreshold(0.3);

            // Use directly lenient search settings
            $searchOptions = [
                'fuzziness' => 'AUTO:1,4', // Maximum fuzziness from the start
                'prefix_length' => 1, // Very small prefix requirement
                'minimum_should_match' => '20%', // Low match requirement
                'slop' => 3, // High slop for phrase matching
            ];

            // Perform the autocomplete search
            $autocompleteResults = $this->esService->autocompleteProperty(
                $query,
                null,
                $searchOptions,
            );

            // Create standard API response structure
            $response = [
                'status' => 'success',
                'code' => 200,
                'message' => 'Autocomplete results retrieved successfully',
                'data' => [
                    'suggestions' => $autocompleteResults,
                ],
                'meta' => [
                    'timestamp' => time(),
                    'version' => '1.0',
                ],
            ];

            return $this->respond($response);
        } catch (\Exception $e) {
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
                'message' => 'Error performing search',
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
