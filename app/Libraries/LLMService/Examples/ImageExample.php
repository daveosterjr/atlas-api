<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use DealMachineApi\LLMService\LLMService;

// Replace with your actual OpenAI API key
$apiKey = 'your-openai-api-key';

// Create a new LLMService instance with GPT-4o (which supports image input)
$llm = new LLMService($apiKey, 'openai', 'gpt-4o');

// Set a system prompt
$llm->setSystemPrompt('You are a helpful AI assistant that can analyze images and provide concise descriptions.');

// Example with an image
$imageUrl = 'https://example.com/path/to/your/image.jpg'; // Replace with a real image URL
$question = 'What can you see in this image?';

// Ask about the image
$response = $llm->ask($question, [$imageUrl]);

echo "Question about image: {$question}\n";
echo "Response: {$response}\n\n";

// Example with multiple images
$imageUrls = [
    'https://example.com/path/to/image1.jpg', // Replace with real image URLs
    'https://example.com/path/to/image2.jpg'
];
$multiImageQuestion = 'What are the differences between these two images?';

// Ask about the differences between images
$multiImageResponse = $llm->ask($multiImageQuestion, $imageUrls);

echo "Question about multiple images: {$multiImageQuestion}\n";
echo "Response: {$multiImageResponse}\n"; 