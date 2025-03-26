<?php

// Include the autoloader
require_once __DIR__ . "/../../../../vendor/autoload.php";

use ElasticSearchService\ElasticSearchService;

// Function to return error response
function returnError($message, $errorDetails = null, $code = 500) {
    http_response_code($code);

    $response = [
        "status" => "error",
        "code" => $code,
        "message" => $message,
        "data" => [],
        "meta" => [
            "timestamp" => time(),
            "version" => "1.0",
        ],
    ];

    if ($errorDetails && getenv("APP_ENV") !== "production") {
        $response["debug"] = [
            "error_details" => $errorDetails,
        ];
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

// Get the search query from the request
$query = $_GET["query"] ?? "";

// Configure Elasticsearch
$config = [
    'host' => getenv("ES_HOST") ?: "https://dealmachineproperties-fa453c.es.us-west-2.aws.elastic.cloud:443",
    'api_key' => getenv("ES_API_KEY") ?: "OHBjZHpwVUJPckdLa0JORVlHb2c6VEtGa1ZzYUhpcGpjUlNmNWYydm8yUQ=="
];

try {
    // Create ElasticSearchService instance
    $es = new ElasticSearchService($config);
    
    // Test the connection
    if (!$es->testConnection()) {
        returnError("Failed to connect to Elasticsearch", null, 500);
    }
    
    // Set configuration
    $es->setLimit(10)->setScoreThreshold(0.6);
    
    // Perform autocomplete search
    $autocompleteResults = $es->autocompleteProperty($query);
    
    // Create standard API response structure
    $response = [
        "status" => "success",
        "code" => 200,
        "message" => "Autocomplete results retrieved successfully",
        "data" => [
            "suggestions" => $autocompleteResults,
        ],
        "meta" => [
            "timestamp" => time(),
            "version" => "1.0",
        ],
    ];
    
    // Return the JSON response
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (\Exception $e) {
    $errorDetails = [
        "message" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "trace" => $e->getTraceAsString(),
    ];
    
    returnError("Error performing search", $errorDetails, 500);
} 