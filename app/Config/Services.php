<?php

namespace Config;

use App\Libraries\LLMService\LLMService;
use App\Libraries\ElasticSearchService\ElasticSearchService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    /**
     * Return an instance of the LLMService
     *
     * @param bool $getShared
     * @param string $apiKey
     * @param string $provider
     * @param string $defaultModel
     * @return LLMService
     */
    public static function llm($getShared = true, ?string $apiKey = null, string $provider = 'openai', string $defaultModel = 'gpt-4o-mini')
    {
        if ($getShared) {
            return static::getSharedInstance('llm', $apiKey, $provider, $defaultModel);
        }

        // Get API key from environment if not provided
        $apiKey = $apiKey ?? getenv('LLM_API_KEY');
        
        return new LLMService($apiKey, $provider, $defaultModel);
    }

    /**
     * Return an instance of the ElasticSearchService
     *
     * @param bool $getShared
     * @param array|null $config
     * @param string $provider
     * @param string $defaultIndex
     * @return ElasticSearchService
     */
    public static function elasticsearch($getShared = true, ?array $config = null, string $provider = 'elasticsearch', string $defaultIndex = 'properties')
    {
        if ($getShared) {
            return static::getSharedInstance('elasticsearch', $config, $provider, $defaultIndex);
        }

        // Get config from environment if not provided
        if ($config === null) {
            $config = [
                'host' => getenv('ES_HOST') ?: 'localhost:9200',
                'api_key' => getenv('ES_API_KEY') ?: ''
            ];
        }
        
        return new ElasticSearchService($config, $provider, $defaultIndex);
    }
}
