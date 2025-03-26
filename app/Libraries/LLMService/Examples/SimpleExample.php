<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use DealMachineApi\LLMService\LLMService;

// Replace with your actual OpenAI API key
$apiKey = 'your-openai-api-key';

// Create a new LLMService instance
$llm = new LLMService($apiKey);

// Set a system prompt
$llm->setSystemPrompt('You are a helpful AI assistant that provides concise responses.');

// Ask a simple question
$question = 'What is the capital of France?';
$response = $llm->ask($question);

echo "Question: {$question}\n";
echo "Response: {$response}\n\n";

// Ask a yes/no question
$yesNoQuestion = 'Is Paris the capital of France?';
$yesNoResponse = $llm->askYesNo($yesNoQuestion);

echo "Yes/No Question: {$yesNoQuestion}\n";
echo "Response: " . ($yesNoResponse ? 'Yes' : 'No') . "\n\n";

// Get a JSON response
$jsonQuestion = 'List the three largest cities in France with their population.';
$jsonSchema = [
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

$jsonResponse = $llm->askForJson($jsonQuestion, $jsonSchema);

echo "JSON Question: {$jsonQuestion}\n";
echo "JSON Response: " . json_encode($jsonResponse, JSON_PRETTY_PRINT) . "\n"; 