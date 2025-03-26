<?php

namespace App\Libraries\ElasticSearchService;

use App\Libraries\ElasticSearchService\Providers\ElasticSearchProvider;
use Exception;
use InvalidArgumentException;

/**
 * ElasticSearchService - A flexible wrapper for Elasticsearch services
 * 
 * This class provides a unified interface for interacting with Elasticsearch,
 * focusing on property searches and autocomplete functionality.
 */
class ElasticSearchService
{
    /**
     * @var array Configuration options
     */
    private array $config;
    
    /**
     * @var string The ElasticSearch provider (default: 'elasticsearch')
     */
    private string $provider;
    
    /**
     * @var string The default index to search
     */
    private string $defaultIndex;
    
    /**
     * @var array Provider implementations
     */
    private array $providers = [];
    
    /**
     * @var int The maximum number of results to return
     */
    private int $limit = 10;
    
    /**
     * @var float The score threshold as a percentage of the top result (0-1)
     */
    private float $scoreThreshold = 0.6;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options (host, api_key, etc.)
     * @param string $provider The ElasticSearch provider (default: 'elasticsearch')
     * @param string $defaultIndex The default index to search (default: 'properties')
     */
    public function __construct(array $config, string $provider = 'elasticsearch', string $defaultIndex = 'properties')
    {
        $this->config = $config;
        $this->provider = $provider;
        $this->defaultIndex = $defaultIndex;
        $this->initializeProviders();
    }
    
    /**
     * Initialize the providers
     */
    private function initializeProviders(): void
    {
        // Register providers
        $this->providers['elasticsearch'] = new ElasticSearchProvider();
        
        // Initialize the selected provider
        if (isset($this->providers[$this->provider])) {
            $this->providers[$this->provider]->initialize($this->config);
        } else {
            throw new InvalidArgumentException("Unsupported provider: {$this->provider}");
        }
    }
    
    /**
     * Test the connection to ElasticSearch
     * 
     * @return bool True if connection successful
     */
    public function testConnection(): bool
    {
        return $this->providers[$this->provider]->testConnection();
    }
    
    /**
     * Set the current provider
     * 
     * @param string $provider The provider name
     * @return self
     */
    public function setProvider(string $provider): self
    {
        if (!isset($this->providers[$provider])) {
            throw new InvalidArgumentException("Unsupported provider: {$provider}");
        }
        
        $this->provider = $provider;
        $this->providers[$provider]->initialize($this->config);
        
        return $this;
    }
    
    /**
     * Set the default index
     * 
     * @param string $index The index name
     * @return self
     */
    public function setIndex(string $index): self
    {
        $this->defaultIndex = $index;
        return $this;
    }
    
    /**
     * Set the maximum number of results to return
     * 
     * @param int $limit The maximum number of results
     * @return self
     */
    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set the score threshold as a percentage of the top result
     * 
     * @param float $threshold The threshold (0-1)
     * @return self
     */
    public function setScoreThreshold(float $threshold): self
    {
        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException("Threshold must be between 0 and 1");
        }
        $this->scoreThreshold = $threshold;
        return $this;
    }
    
    /**
     * Get available indices
     * 
     * @return array List of available indices
     */
    public function getIndices(): array
    {
        return $this->providers[$this->provider]->getIndices();
    }
    
    /**
     * Get mapping for an index
     * 
     * @param string|null $index The index name (or use default)
     * @return array The mapping structure
     */
    public function getMapping(?string $index = null): array
    {
        $indexName = $index ?? $this->defaultIndex;
        return $this->providers[$this->provider]->getMapping($indexName);
    }
    
    /**
     * Perform an autocomplete search for properties
     * 
     * @param string $query The search query
     * @param string|null $index The index to search (or use default)
     * @return array The search results formatted for autocomplete
     */
    public function autocompleteProperty(string $query, ?string $index = null): array
    {
        if (empty(trim($query))) {
            return [];
        }
        
        $indexName = $index ?? $this->defaultIndex;
        
        $searchParams = [
            "size" => $this->limit,
            "query" => [
                "bool" => [
                    "must" => [
                        [
                            "multi_match" => [
                                "query" => $query,
                                "fields" => [
                                    "propertyaddress_address^5",
                                    "propertyaddress_city^1.5",
                                    "propertyaddress_state",
                                    "propertyaddress_Zip^2"
                                ],
                                "type" => "best_fields",
                                "fuzziness" => "AUTO:3,6",
                                "prefix_length" => 3,
                                "minimum_should_match" => "50%"
                            ]
                        ]
                    ],
                    "should" => [
                        [
                            "match_phrase_prefix" => [
                                "propertyaddress_address" => [
                                    "query" => $query,
                                    "boost" => 10,
                                    "slop" => 1
                                ]
                            ]
                        ],
                        [
                            "match_phrase" => [
                                "propertyaddress_address" => [
                                    "query" => $query,
                                    "boost" => 3,
                                    "slop" => 0
                                ]
                            ]
                        ]
                    ],
                    "minimum_should_match" => 1
                ]
            ],
            "highlight" => [
                "fields" => [
                    "propertyaddress_address" => new \stdClass(),
                    "propertyaddress_city" => new \stdClass(),
                    "propertyaddress_state" => new \stdClass(),
                    "propertyaddress_Zip" => new \stdClass()
                ]
            ],
            "sort" => [
                "_score" => [
                    "order" => "desc"
                ]
            ]
        ];
        
        $searchResponse = $this->providers[$this->provider]->search($indexName, $searchParams);
        return $this->formatAutocompleteResults($searchResponse);
    }
    
    /**
     * Format raw search results for autocomplete
     * 
     * @param array $searchResponse The raw search response
     * @return array Formatted autocomplete results
     */
    private function formatAutocompleteResults(array $searchResponse): array
    {
        $hits = $searchResponse["hits"]["hits"] ?? [];
        $autocompleteResults = [];
        
        // Calculate score threshold for including results
        $topScore = !empty($hits) ? $hits[0]["_score"] : 0;
        $threshold = $topScore * $this->scoreThreshold;
        
        foreach ($hits as $hit) {
            // Skip low-scoring results
            if (isset($hit["_score"]) && $hit["_score"] < $threshold) {
                continue;
            }
            
            $source = $hit["_source"];
            $originalScore = $hit["_score"] ?? 0;
            
            // Initialize default text and subtext
            $text = "";
            $subtext = "";
            
            // Determine suggestion type based on available fields
            $suggestion_type = "unknown";
            
            if (isset($source["attr_property_data_id"])) {
                $suggestion_type = "properties";
            }
            
            // Format the address as text and subtext based on suggestion type
            if ($suggestion_type === "properties") {
                $text = $source["propertyaddress_address"] ?? "";
                $subtext = "";
                $cityState = [];
                if (!empty($source["propertyaddress_city"])) {
                    $cityState[] = $source["propertyaddress_city"];
                }
                if (!empty($source["propertyaddress_state"])) {
                    $cityState[] = $source["propertyaddress_state"];
                }
                
                $locationParts = [];
                if (!empty($cityState)) {
                    $locationParts[] = implode(", ", $cityState);
                }
                if (!empty($source["propertyaddress_Zip"])) {
                    $locationParts[] = $source["propertyaddress_Zip"];
                }
                
                $subtext = implode(" ", $locationParts);
            } else {
                // Default fallback for unknown types
                $text = $source["propertyaddress_address"] ?? "Unknown";
                $subtext = "Unknown type";
            }
            
            $result = [
                "text" => $text,
                "subtext" => $subtext,
                "suggestion_type" => $suggestion_type,
            ];
            
            // Determine the appropriate ID field based on suggestion type
            if ($suggestion_type === "properties" && isset($source["attr_property_data_id"])) {
                $result["id"] = $source["attr_property_data_id"];
            } else {
                // Fallback to Elasticsearch document ID
                $result["id"] = $hit["_id"];
            }
            
            // Add highlight if available
            if (isset($hit["highlight"])) {
                $result["highlight"] = $hit["highlight"];
            }
            
            // Store original score for debugging
            $result["original_score"] = $originalScore;
            
            $autocompleteResults[] = $result;
        }
        
        return $autocompleteResults;
    }
    
    /**
     * Perform a custom search query
     * 
     * @param array $searchParams The search parameters
     * @param string|null $index The index to search (or use default)
     * @return array The search response
     */
    public function search(array $searchParams, ?string $index = null): array
    {
        $indexName = $index ?? $this->defaultIndex;
        return $this->providers[$this->provider]->search($indexName, $searchParams);
    }
} 