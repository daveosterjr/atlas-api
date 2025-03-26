<?php

namespace App\Config;

use CodeIgniter\Config\BaseConfig;

class ElasticSearch extends BaseConfig {
    /**
     * ElasticSearch host
     */
    public string $host = 'localhost:9200';

    /**
     * ElasticSearch API key
     */
    public string $apiKey = '';

    /**
     * Default provider to use
     */
    public string $provider = 'elasticsearch';

    /**
     * Default index to search
     */
    public string $defaultIndex = 'properties';

    /**
     * Default result limit
     */
    public int $limit = 10;

    /**
     * Default score threshold (0-1)
     */
    public float $scoreThreshold = 0.6;

    public function __construct() {
        parent::__construct();

        // Load from environment variables if available
        $this->host = getenv('ES_HOST') ?: $this->host;
        $this->apiKey = getenv('ES_API_KEY') ?: $this->apiKey;
    }
}
