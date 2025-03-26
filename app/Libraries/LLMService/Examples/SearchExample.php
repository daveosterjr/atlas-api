<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use LLMService\LLMService;

// Replace with your actual OpenAI API key
$apiKey = 'your-openai-api-key';

// Create a new LLMService instance
$llm = new LLMService($apiKey);

// Set a system prompt
$llm->setSystemPrompt('You are a helpful AI assistant that provides accurate, up-to-date information by searching the web when necessary.');

// Check if the model supports search
$searchModel = 'gpt-4o-search-preview';
if (!$llm->modelSupportsSearch($searchModel)) {
    echo "Error: Model {$searchModel} does not support web search.\n";
    exit;
}

// Example 1: Ask a question about current events using web search
$currentEventQuestion = 'What are the major headlines in tech news today?';
echo "Question: {$currentEventQuestion}\n";
echo "Searching the web for an answer...\n";

$response = $llm->askWithSearch($currentEventQuestion);
echo "Response: {$response}\n\n";

// Example 2: Ask a factual question that might benefit from latest information
$factualQuestion = 'What is the current price of Bitcoin?';
echo "Question: {$factualQuestion}\n";
echo "Searching the web for an answer...\n";

$response = $llm->askWithSearch($factualQuestion);
echo "Response: {$response}\n\n";

// Example 3: Ask a more complex research question
$researchQuestion = 'What are the latest advancements in quantum computing?';
echo "Question: {$researchQuestion}\n";
echo "Searching the web for an answer...\n";

$response = $llm->askWithSearch($researchQuestion);
echo "Response: {$response}\n\n";

// You can also use the search model with a custom temperature
$llm->setTemperature(0.7); // Adjust for more creative responses
$customQuestion = 'Who won the last Super Bowl and what were some interesting facts about the game?';
echo "Question: {$customQuestion}\n";
echo "Searching the web for an answer with custom temperature...\n";

$response = $llm->askWithSearch($customQuestion);
echo "Response: {$response}\n"; 