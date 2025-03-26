<?php

require __DIR__ . '/vendor/autoload.php';

// Get environment variables
$esHost =
    getenv('ES_HOST') ?: 'https://dealmachineproperties-fa453c.es.us-west-2.aws.elastic.cloud:443';
$esApiKey = getenv('ES_API_KEY') ?: 'OHBjZHpwVUJPckdLa0JORVlHb2c6VEtGa1ZzYUhpcGpjUlNmNWYydm8yUQ==';

echo "Testing Elasticsearch connection...\n";
echo "Host: {$esHost}\n";
echo 'API Key: ' . (empty($esApiKey) ? 'Not set' : 'Set') . "\n\n";

try {
    // Verify the ClientBuilder class exists
    echo 'Checking if ClientBuilder class exists... ';
    if (class_exists('\\Elastic\\Elasticsearch\\ClientBuilder')) {
        echo "YES\n";
    } else {
        echo "NO\n";
        exit(1);
    }

    echo "Creating Elasticsearch client...\n";
    $client = \Elastic\Elasticsearch\ClientBuilder::create()
        ->setHosts([$esHost])
        ->setApiKey($esApiKey)
        ->build();

    echo "Testing connection...\n";
    $response = $client->info();
    $info = $response->asArray();

    echo "Connection successful!\n";
    echo 'Elasticsearch version: ' . $info['version']['number'] . "\n";
    echo 'Cluster name: ' . $info['cluster_name'] . "\n";

    // Test a simple search
    echo "\nTesting search functionality...\n";
    $searchParams = [
        'index' => 'properties',
        'body' => [
            'size' => 1,
            'query' => [
                'match_all' => new \stdClass(),
            ],
        ],
    ];

    $searchResponse = $client->search($searchParams);
    $searchResults = $searchResponse->asArray();

    echo 'Search successful. Found ' .
        $searchResults['hits']['total']['value'] .
        " total documents.\n";
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    echo 'File: ' . $e->getFile() . ' (Line: ' . $e->getLine() . ")\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
