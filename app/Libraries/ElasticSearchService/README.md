# ElasticSearchService

A flexible PHP wrapper for Elasticsearch services, designed for property searches and autocomplete functionality.

## Features

- **Unified Interface**: Interact with Elasticsearch through a simple interface
- **Autocomplete**: Built-in support for property address autocomplete
- **Configurable**: Set limits, thresholds, and other search parameters
- **Provider Pattern**: Extensible architecture for different Elasticsearch implementations
- **Index Management**: Get information about available indices and mappings
- **Custom Searches**: Perform custom search queries beyond the built-in methods

## Requirements

- PHP 7.4 or higher
- Elasticsearch PHP Client library (`elastic/elasticsearch`)

## Installation

1. Add the ElasticSearchService library to your project
2. Install the required dependencies through Composer

```bash
composer require elastic/elasticsearch
```

## Basic Usage

### Creating an Instance

```php
// Configure Elasticsearch connection
$config = [
    'host' => 'https://your-elasticsearch-host:443',
    'api_key' => 'your-elasticsearch-api-key'
];

// Create a new ElasticSearchService instance (defaults to 'elasticsearch' provider and 'properties' index)
$es = new ElasticSearchService($config);

// Or specify a different provider and index
$es = new ElasticSearchService($config, 'elasticsearch', 'custom_index');
```

### Testing Connection

```php
// Check if the connection is working
if ($es->testConnection()) {
    echo "Successfully connected to Elasticsearch!";
} else {
    echo "Failed to connect to Elasticsearch.";
}
```

### Property Autocomplete

```php
// Perform an autocomplete search for properties
$results = $es->autocompleteProperty('123 Main');

// Process the results
foreach ($results as $result) {
    echo "Address: {$result['text']}\n";
    echo "Location: {$result['subtext']}\n";
    echo "ID: {$result['id']}\n";
    echo "-------------------\n";
}
```

### Advanced Configuration

```php
// Set the maximum number of results to return
$es->setLimit(20);

// Set the score threshold (0-1) as a percentage of the top result
$es->setScoreThreshold(0.5);

// Change the index to search
$es->setIndex('different_index');
```

### Working with Indices

```php
// Get information about available indices
$indices = $es->getIndices();
print_r($indices);

// Get mapping for the default index
$mapping = $es->getMapping();
print_r($mapping);

// Get mapping for a specific index
$mapping = $es->getMapping('custom_index');
print_r($mapping);
```

### Custom Searches

```php
// Define custom search parameters
$searchParams = [
    "query" => [
        "match" => [
            "propertyaddress_city" => "New York"
        ]
    ],
    "sort" => [
        "propertyaddress_Zip" => [
            "order" => "asc"
        ]
    ]
];

// Execute the custom search
$response = $es->search($searchParams);
print_r($response);
```

## Extending with New Providers

To add a new Elasticsearch provider:

1. Create a new class implementing the `ElasticSearchProviderInterface`
2. Register the provider in the `ElasticSearchService::initializeProviders()` method

## Examples

Check the code for detailed examples of how to use the ElasticSearchService. 