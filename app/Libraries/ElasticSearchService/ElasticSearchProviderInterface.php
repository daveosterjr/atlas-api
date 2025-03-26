<?php

namespace App\Libraries\ElasticSearchService;

/**
 * Interface for ElasticSearch Provider implementations
 */
interface ElasticSearchProviderInterface
{
    /**
     * Initialize the client with configuration
     * 
     * @param array $config Configuration options (host, api key, etc.)
     * @return void
     */
    public function initialize(array $config): void;
    
    /**
     * Test the connection to ElasticSearch
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool;
    
    /**
     * Perform a search query
     * 
     * @param string $index The index to search
     * @param array $searchParams The search parameters
     * @return array The search results
     */
    public function search(string $index, array $searchParams): array;
    
    /**
     * Get information about available indices
     * 
     * @return array List of available indices
     */
    public function getIndices(): array;
    
    /**
     * Get mapping for a specific index
     * 
     * @param string $index The index name
     * @return array The mapping structure
     */
    public function getMapping(string $index): array;
} 