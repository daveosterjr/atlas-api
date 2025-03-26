<?php

namespace App\Libraries\ElasticSearch;

use App\Config\ElasticSearch as ElasticSearchConfig;
use Exception;
use InvalidArgumentException;

/**
 * ElasticSearchService - A wrapper for Elasticsearch operations
 *
 * This class provides a unified interface for interacting with Elasticsearch,
 * focusing on property searches and autocomplete functionality.
 */
class ElasticSearchService {
    /**
     * @var ElasticSearchConfig The configuration
     */
    protected $config;

    /**
     * @var ElasticSearchProvider The provider implementation
     */
    protected $provider;

    /**
     * Constructor
     *
     * @param mixed $config Configuration object or null
     */
    public function __construct($config = null) {
        // Handle the case where BaseService passes false as config
        if (!($config instanceof ElasticSearchConfig) && $config !== null) {
            $config = null;
        }

        $this->config = $config ?? config('ElasticSearch');
        $this->provider = new ElasticSearchProvider($this->config);
    }

    /**
     * Test the connection to ElasticSearch
     *
     * @return bool True if connection successful
     */
    public function testConnection(): bool {
        return $this->provider->testConnection();
    }

    /**
     * Set the maximum number of results to return
     *
     * @param int $limit The maximum number of results
     * @return self
     */
    public function setLimit(int $limit): self {
        $this->config->limit = $limit;
        return $this;
    }

    /**
     * Set the score threshold as a percentage of the top result
     *
     * @param float $threshold The threshold (0-1)
     * @return self
     */
    public function setScoreThreshold(float $threshold): self {
        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Threshold must be between 0 and 1');
        }
        $this->config->scoreThreshold = $threshold;
        return $this;
    }

    /**
     * Get available indices
     *
     * @return array List of available indices
     */
    public function getIndices(): array {
        return $this->provider->getIndices();
    }

    /**
     * Get mapping for an index
     *
     * @param string|null $index The index name (or use default)
     * @return array The mapping structure
     */
    public function getMapping(?string $index = null): array {
        $indexName = $index ?? $this->config->defaultIndex;
        return $this->provider->getMapping($indexName);
    }

    /**
     * Perform an autocomplete search for properties
     *
     * @param string $query The search query
     * @param string|null $index The index to search (or use default)
     * @param array $searchOptions Optional search parameters to override defaults
     * @return array The search results formatted for autocomplete
     */
    public function autocompleteProperty(
        string $query,
        ?string $index = null,
        array $searchOptions = [],
    ): array {
        if (empty(trim($query))) {
            return [];
        }

        $indexName = $index ?? $this->config->defaultIndex;

        // Default search parameters
        $defaults = [
            'fuzziness' => 'AUTO:3,6',
            'prefix_length' => 3,
            'minimum_should_match' => '50%',
            'slop' => 1,
        ];

        // Merge custom options with defaults
        $options = array_merge($defaults, $searchOptions);

        $searchParams = [
            'size' => $this->config->limit,
            'query' => [
                'bool' => [
                    'should' => [
                        // Primary fuzzy multi-match for general matching
                        [
                            'multi_match' => [
                                'query' => $query,
                                'fields' => [
                                    'propertyaddress_address^5',
                                    'propertyaddress_city^1.5',
                                    'propertyaddress_state',
                                    'propertyaddress_Zip^2',
                                ],
                                'type' => 'best_fields',
                                'fuzziness' => $options['fuzziness'],
                                'prefix_length' => $options['prefix_length'],
                                'minimum_should_match' => $options['minimum_should_match'],
                                'boost' => 1.5,
                            ],
                        ],
                        // Phrase prefix match for catching beginning of words/phrases
                        [
                            'match_phrase_prefix' => [
                                'propertyaddress_address' => [
                                    'query' => $query,
                                    'boost' => 10,
                                    'slop' => $options['slop'],
                                    'max_expansions' => 50,
                                ],
                            ],
                        ],
                        // Exact phrase match with slop for word order flexibility
                        [
                            'match_phrase' => [
                                'propertyaddress_address' => [
                                    'query' => $query,
                                    'boost' => 3,
                                    'slop' => $options['slop'],
                                ],
                            ],
                        ],
                        // Term matching for exact tokens
                        [
                            'terms' => [
                                'propertyaddress_address' => explode(' ', strtolower($query)),
                                'boost' => 1.2,
                            ],
                        ],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
            'highlight' => [
                'fields' => [
                    'propertyaddress_address' => new \stdClass(),
                    'propertyaddress_city' => new \stdClass(),
                    'propertyaddress_state' => new \stdClass(),
                    'propertyaddress_Zip' => new \stdClass(),
                ],
            ],
            'sort' => [
                '_score' => [
                    'order' => 'desc',
                ],
            ],
        ];

        $searchResponse = $this->provider->search($indexName, $searchParams);
        return $this->formatAutocompleteResults($searchResponse);
    }

    /**
     * Format raw search results for autocomplete
     *
     * @param array $searchResponse The raw search response
     * @return array Formatted autocomplete results
     */
    protected function formatAutocompleteResults(array $searchResponse): array {
        $results = [];

        if (!isset($searchResponse['hits']['hits']) || empty($searchResponse['hits']['hits'])) {
            return $results;
        }

        $hits = $searchResponse['hits']['hits'];

        // Get the highest score for threshold calculation
        $maxScore = !empty($hits) ? $hits[0]['_score'] : 0;
        $minScoreThreshold = $maxScore * $this->config->scoreThreshold;

        foreach ($hits as $hit) {
            // Skip results below the threshold
            if ($hit['_score'] < $minScoreThreshold) {
                continue;
            }

            $source = $hit['_source'];

            // Extract address components
            $address = $source['propertyaddress_address'] ?? '';
            $city = $source['propertyaddress_city'] ?? '';
            $state = $source['propertyaddress_state'] ?? '';
            $zip = $source['propertyaddress_Zip'] ?? '';

            // Build the suggestion
            $suggestion = [
                'id' => $hit['_id'],
                'text' => trim("$address, $city, $state $zip"),
                'score' => $hit['_score'],
                'address' => [
                    'street' => $address,
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                ],
            ];

            // Add highlights if available
            if (isset($hit['highlight'])) {
                $suggestion['highlight'] = $hit['highlight'];
            }

            $results[] = $suggestion;
        }

        return $results;
    }

    /**
     * Get the current configuration
     *
     * @return ElasticSearchConfig
     */
    public function getConfig(): ElasticSearchConfig {
        return $this->config;
    }
}
