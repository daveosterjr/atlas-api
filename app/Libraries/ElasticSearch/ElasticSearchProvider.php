<?php

namespace App\Libraries\ElasticSearch;

use App\Config\ElasticSearch as ElasticSearchConfig;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;

/**
 * ElasticSearch Provider implementation
 */
class ElasticSearchProvider {
    /**
     * @var \Elastic\Elasticsearch\Client The ElasticSearch client
     */
    protected $client;

    /**
     * @var ElasticSearchConfig The configuration
     */
    protected $config;

    /**
     * Constructor
     */
    public function __construct(ElasticSearchConfig $config) {
        $this->config = $config;
        $this->initialize();
    }

    /**
     * Initialize the client with configuration
     *
     * @return void
     */
    protected function initialize(): void {
        $builder = ClientBuilder::create();

        // Set the host
        $builder->setHosts([$this->config->host]);

        // Set API key if provided
        if (!empty($this->config->apiKey)) {
            $builder->setApiKey($this->config->apiKey);
        }

        // Build the client
        $this->client = $builder->build();
    }

    /**
     * Test the connection to ElasticSearch
     *
     * @return bool True if connection successful
     */
    public function testConnection(): bool {
        try {
            $info = $this->client->info();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Perform a search query
     *
     * @param string $index The index to search
     * @param array $searchParams The search parameters
     * @return array The search results
     */
    public function search(string $index, array $searchParams): array {
        $params = [
            'index' => $index,
            'body' => $searchParams,
        ];

        $response = $this->client->search($params);
        return $response->asArray();
    }

    /**
     * Get information about available indices
     *
     * @return array List of available indices
     */
    public function getIndices(): array {
        $response = $this->client->cat()->indices(['format' => 'json']);
        return $response->asArray();
    }

    /**
     * Get mapping for a specific index
     *
     * @param string $index The index name
     * @return array The mapping structure
     */
    public function getMapping(string $index): array {
        $response = $this->client->indices()->getMapping(['index' => $index]);
        return $response->asArray();
    }
}
