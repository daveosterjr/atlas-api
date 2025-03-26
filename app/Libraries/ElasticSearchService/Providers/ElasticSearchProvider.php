<?php

namespace App\Libraries\ElasticSearchService\Providers;

use App\Libraries\ElasticSearchService\ElasticSearchProviderInterface;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;

/**
 * Default ElasticSearch Provider implementation
 */
class ElasticSearchProvider implements ElasticSearchProviderInterface
{
    /**
     * @var \Elastic\Elasticsearch\Client The ElasticSearch client
     */
    private $client;
    
    /**
     * Initialize the client with configuration
     * 
     * @param array $config Configuration options (host, api key, etc.)
     * @return void
     */
    public function initialize(array $config): void
    {
        $builder = ClientBuilder::create();
        
        if (isset($config['host'])) {
            $builder->setHosts([$config['host']]);
        }
        
        if (isset($config['api_key'])) {
            $builder->setApiKey($config['api_key']);
        }
        
        // Add additional configuration options as needed
        
        $this->client = $builder->build();
    }
    
    /**
     * Test the connection to ElasticSearch
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool
    {
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
    public function search(string $index, array $searchParams): array
    {
        $params = [
            'index' => $index,
            'body' => $searchParams
        ];
        
        $response = $this->client->search($params);
        return $response->asArray();
    }
    
    /**
     * Get information about available indices
     * 
     * @return array List of available indices
     */
    public function getIndices(): array
    {
        $response = $this->client->cat()->indices(['format' => 'json']);
        return $response->asArray();
    }
    
    /**
     * Get mapping for a specific index
     * 
     * @param string $index The index name
     * @return array The mapping structure
     */
    public function getMapping(string $index): array
    {
        $response = $this->client->indices()->getMapping(['index' => $index]);
        return $response->asArray();
    }
} 