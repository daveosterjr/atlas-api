# LLMService

A flexible PHP wrapper for Large Language Model (LLM) services, starting with OpenAI but designed to be extensible to other LLM providers.

## Features

- **Unified Interface**: Interact with different LLM providers through a single interface
- **Conversation Management**: Maintain conversation history for context-aware responses
- **System Prompts**: Set system prompts to guide the behavior of the LLM
- **JSON Responses**: Request responses in JSON format
- **Boolean Responses**: Ask yes/no questions and get boolean responses
- **Image Support**: Attach images to your prompts (for models that support it)
- **Web Search**: Use models with web search capability to get real-time information
- **Model Control**: Choose between different models (GPT-4o-mini, GPT-4o, etc.)
- **Extensible**: Easy to add support for additional LLM providers

## Requirements

- PHP 7.4 or higher
- OpenAI PHP Client library (`openai-php/client`)
- Guzzle HTTP Client (`guzzlehttp/guzzle`)

## Installation

1. Add the LLMService library to your project
2. Install the required dependencies through Composer

```bash
composer require openai-php/client
```

## Basic Usage

### Creating an Instance

```php
// Replace with your actual OpenAI API key
$apiKey = 'your-openai-api-key';

// Create a new LLMService instance (defaults to OpenAI and GPT-4o-mini)
$llm = new LLMService($apiKey);

// Or specify a different provider and model
$llm = new LLMService($apiKey, 'openai', 'gpt-4o');
```

### Asking a Simple Question

```php
// Set a system prompt (optional)
$llm->setSystemPrompt('You are a helpful AI assistant.');

// Ask a question
$response = $llm->ask('What is the capital of France?');
echo $response; // "The capital of France is Paris."
```

### Getting a Boolean Response

```php
// Ask a yes/no question
$isTrue = $llm->askYesNo('Is Paris the capital of France?');
echo $isTrue ? 'Yes' : 'No'; // "Yes"
```

### Getting a JSON Response

```php
// Define the JSON schema you want to receive
$schema = [
    'type' => 'object',
    'properties' => [
        'cities' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'population' => ['type' => 'number']
                ]
            ]
        ]
    ]
];

// Ask for a JSON response
$jsonResponse = $llm->askForJson(
    'List the three largest cities in France with their population.',
    $schema
);

// $jsonResponse is now an associative array
print_r($jsonResponse);
```

### Working with Images

```php
// Make sure to use a model that supports images
$llm->setModel('gpt-4o');

// Ask a question with an image
$response = $llm->ask(
    'What can you see in this image?',
    ['https://example.com/path/to/image.jpg']
);

// You can also include multiple images
$response = $llm->ask(
    'Compare these two images.',
    [
        'https://example.com/path/to/image1.jpg',
        'https://example.com/path/to/image2.jpg'
    ]
);
```

### Managing Conversations

```php
// Conversations are automatically maintained
$llm->ask('What is the capital of France?');
$llm->ask('What is its population?'); // The AI knows "its" refers to Paris

// Clear the conversation history if needed
$llm->clearConversation();
```

### Advanced Configuration

```php
// Set the temperature (0-2, higher = more creative)
$llm->setTemperature(0.7);

// Set the maximum number of tokens to generate
$llm->setMaxTokens(2000);

// Get available models for the current provider
$models = $llm->getAvailableModels();
```

### Using Web Search Capability

```php
// Make sure to use a model that supports web search
$llm->setModel('gpt-4o-search-preview');

// Ask a question that benefits from real-time information
$response = $llm->askWithSearch('What are the latest tech news headlines?');

// You can also check if a model supports search
if ($llm->modelSupportsSearch('gpt-4o-search-preview')) {
    // Use search capabilities
}

// Web search works with images too (if the model supports both)
$response = $llm->askWithSearch(
    'What can you tell me about this product? Is it well-reviewed online?',
    null,
    ['https://example.com/path/to/product.jpg']
);
```

## Extending with New Providers

To add a new LLM provider:

1. Create a new class implementing the `LLMProviderInterface`
2. Register the provider in the `LLMService::initializeProviders()` method

## Examples

See the `Examples` directory for more detailed usage examples. 