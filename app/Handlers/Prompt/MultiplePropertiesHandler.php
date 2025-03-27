<?php

namespace App\Handlers\Prompt;

use App\Handlers\PromptHandlerInterface;
use App\Libraries\LLMService\LLMService;
use App\Libraries\ElasticSearch\ElasticSearchService;
use App\Config\AllFilters;
use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class MultiplePropertiesHandler implements PromptHandlerInterface {
    /**
     * @var LLMService
     */
    protected $llmService;

    /**
     * @var ElasticSearchService
     */
    protected $esService;

    /**
     * JSON schema for filter validation
     *
     * @var array
     */
    protected $filterSchema;

    /**
     * Constructor
     *
     * @param LLMService $llmService
     * @param ElasticSearchService $esService
     */
    public function __construct(LLMService $llmService, ElasticSearchService $esService) {
        $this->llmService = $llmService;
        $this->esService = $esService;
        $this->initFilterSchema();
    }

    /**
     * Initialize filter schema for validation
     */
    private function initFilterSchema() {
        $this->filterSchema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['id', 'type', 'source_type', 'label', 'filterType', 'value'],
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['number', 'text', 'bool', 'date', 'multiselect'],
                    ],
                    'subtype' => ['type' => ['string', 'null']],
                    'source_type' => ['type' => 'string', 'enum' => ['properties', 'contacts']],
                    'label' => ['type' => 'string'],
                    'filterType' => ['type' => 'string'],
                    'value' => ['type' => ['object', 'string', 'number', 'boolean']],
                ],
                'allOf' => [
                    [
                        'if' => [
                            'properties' => [
                                'type' => ['enum' => ['number']],
                            ],
                        ],
                        'then' => [
                            'properties' => [
                                'filterType' => [
                                    'enum' => ['range', 'gt', 'gte', 'lt', 'lte', 'eq', 'neq'],
                                ],
                                'value' => [
                                    'oneOf' => [
                                        // For eq: direct number value
                                        ['type' => 'number'],
                                        // For range: object with min and max
                                        [
                                            'type' => 'object',
                                            'required' => ['min', 'max'],
                                            'properties' => [
                                                'min' => ['type' => 'number'],
                                                'max' => ['type' => 'number'],
                                            ],
                                        ],
                                        // For comparison operators: object with operator key
                                        [
                                            'type' => 'object',
                                            'properties' => [
                                                'gt' => ['type' => 'number'],
                                                'gte' => ['type' => 'number'],
                                                'lt' => ['type' => 'number'],
                                                'lte' => ['type' => 'number'],
                                                'neq' => ['type' => 'number'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'if' => [
                            'properties' => [
                                'type' => ['enum' => ['text']],
                            ],
                        ],
                        'then' => [
                            'properties' => [
                                'filterType' => [
                                    'enum' => [
                                        'contains',
                                        'starts_with',
                                        'ends_with',
                                        'equals',
                                        'not_equals',
                                        'not_contains',
                                        'any_of',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'if' => [
                            'properties' => [
                                'type' => ['enum' => ['date']],
                            ],
                        ],
                        'then' => [
                            'properties' => [
                                'filterType' => [
                                    'enum' => [
                                        'date_range',
                                        'is_after',
                                        'is_before',
                                        'is_equal',
                                        'relative_time',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'if' => [
                            'properties' => [
                                'type' => ['enum' => ['multiselect']],
                            ],
                        ],
                        'then' => [
                            'properties' => [
                                'filterType' => ['enum' => ['contains_any', 'contains_none']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Handle multiple properties search
     *
     * @param string $prompt The original prompt
     * @return array The action result
     */
    public function handle(string $prompt, array $appliedFilters = []): array {
        $filters = AllFilters::getPropertyFilters();
        $criteriaData = $this->extractSearchCriteria($prompt, $filters, $appliedFilters);

        return [
            'action_type' => 'get_properties_filters',
            'details' => 'Filtering property listings based on criteria',
            'query' => $prompt,
            'saved_search' => [
                'id' => $criteriaData['search_id'],
                'name' => $criteriaData['search_name'],
                'filters' => $criteriaData['extracted_criteria'],
                'criteria' => $criteriaData,
            ],
        ];
    }

    /**
     * Extract search criteria from the prompt using LLM
     *
     * @param string $prompt The user's search prompt
     * @param array $availableFilters List of available filters
     * @return array Extracted search criteria
     */
    private function extractSearchCriteria(
        string $prompt,
        array $availableFilters,
        array $appliedFilters = [],
    ): array {
        try {
            // Configure LLM settings for filter extraction
            $this->llmService->setSystemPrompt(
                $this->getFilterExtractionSystemPrompt($availableFilters, $appliedFilters),
            );
            $this->llmService->setModel('gpt-4o');
            $this->llmService->setMaxTokens(2000);
            $this->llmService->setTemperature(0.3); // Lower temperature for more focused responses

            // Create the user message with the prompt
            $userMessage = "Extract filters from this prompt and provide a brief explanation: \"$prompt\"";

            // Get structured response with filters and explanation
            try {
                $responseSchema = [
                    'type' => 'object',
                    'required' => ['filters', 'explanation', 'search_name'],
                    'properties' => [
                        'filters' => $this->filterSchema,
                        'explanation' => [
                            'type' => 'object',
                            'required' => ['matched', 'unmatched'],
                            'properties' => [
                                'matched' => ['type' => 'string'],
                                'unmatched' => ['type' => 'string'],
                            ],
                        ],
                        'search_name' => ['type' => 'string'],
                    ],
                ];

                $response = $this->llmService->askForJson($userMessage, $responseSchema);

                // Extract filters, explanation, and search name
                $filters = $response['filters'] ?? [];
                $explanation = $response['explanation'] ?? [
                    'matched' => '',
                    'unmatched' => 'No filters matched.',
                ];
                $searchName =
                    $response['search_name'] ??
                    $this->generateDefaultSearchName($prompt, $appliedFilters);

                // Handle response format - extract the items array if needed
                if (
                    is_array($filters) &&
                    isset($filters['type']) &&
                    isset($filters['items']) &&
                    $filters['type'] === 'array'
                ) {
                    $filters = $filters['items'];
                }

                // Process filters - merge with applied filters and handle updates
                $mergedFilters = $this->validateAndMergeFilters(
                    $filters,
                    $availableFilters,
                    $appliedFilters,
                );

                // Get counts of new and updated filters for reporting
                $newFilterCount = 0;
                $updatedFilterCount = 0;
                $appliedFilterIds = [];

                foreach ($appliedFilters as $filter) {
                    if (isset($filter['id'])) {
                        $appliedFilterIds[$filter['id']] = true;
                    }
                }

                foreach ($filters as $filter) {
                    if (isset($filter['id'])) {
                        if (isset($appliedFilterIds[$filter['id']])) {
                            $updatedFilterCount++;
                        } else {
                            $newFilterCount++;
                        }
                    }
                }

                // Generate a random search ID (for dummy data)
                $searchId = 'search_' . uniqid();

                $result = [
                    'search_type' => 'multiple_properties',
                    'search_id' => $searchId,
                    'search_name' => $searchName,
                    'extracted_criteria' => $mergedFilters,
                    'filter_count' => count($mergedFilters),
                    'new_filter_count' => $newFilterCount,
                    'updated_filter_count' => $updatedFilterCount,
                    'explanation' => $explanation,
                    'applied_filters_count' => count($appliedFilters),
                ];

                // Add original filters for reference/debugging
                if (!empty($filters)) {
                    $result['new_filters'] = $filters;
                }

                return $result;
            } catch (Exception $jsonError) {
                // Fallback to regular ask and manual parsing if JSON structured output fails
                Log::warning(
                    'JSON structured output failed, falling back to text parsing: ' .
                        $jsonError->getMessage(),
                );

                // Standard text response fallback
                $llmResponse = $this->llmService->ask($userMessage);

                // Try to extract JSON object containing filters and explanation
                try {
                    $extractedJson = $this->extractJsonFromResponse($llmResponse);
                    $filters = $extractedJson['filters'] ?? [];
                    $explanation = $extractedJson['explanation'] ?? [
                        'matched' => '',
                        'unmatched' => 'No filters matched.',
                    ];
                    $searchName =
                        $extractedJson['search_name'] ??
                        $this->generateDefaultSearchName($prompt, $appliedFilters);

                    // We don't need to format the explanation, we'll return the raw object structure
                } catch (Exception $e) {
                    // If couldn't extract structured response, try to find just filters
                    preg_match('/```(?:json)?\s*([\s\S]*?)```/', $llmResponse, $matches);
                    $jsonPart = $matches[1] ?? null;

                    if ($jsonPart) {
                        $filters = json_decode($jsonPart, true);
                        // Default explanation using the same structure as used in the main try block
                        $explanation = [
                            'matched' => 'Filters extracted from query',
                            'unmatched' => '',
                        ];
                    } else {
                        $filters = [];
                        $explanation = [
                            'matched' => '',
                            'unmatched' => 'No filters could be extracted',
                        ];
                    }

                    $searchName = $this->generateDefaultSearchName($prompt, $appliedFilters);
                }

                // Handle response format - extract the items array if needed
                if (
                    is_array($filters) &&
                    isset($filters['type']) &&
                    isset($filters['items']) &&
                    $filters['type'] === 'array'
                ) {
                    $filters = $filters['items'];
                }

                // Process filters - merge with applied filters and handle updates
                $mergedFilters = $this->validateAndMergeFilters(
                    $filters,
                    $availableFilters,
                    $appliedFilters,
                );

                // Get counts of new and updated filters for reporting
                $newFilterCount = 0;
                $updatedFilterCount = 0;
                $appliedFilterIds = [];

                foreach ($appliedFilters as $filter) {
                    if (isset($filter['id'])) {
                        $appliedFilterIds[$filter['id']] = true;
                    }
                }

                foreach ($filters as $filter) {
                    if (isset($filter['id'])) {
                        if (isset($appliedFilterIds[$filter['id']])) {
                            $updatedFilterCount++;
                        } else {
                            $newFilterCount++;
                        }
                    }
                }

                // Generate a random search ID (for dummy data)
                $searchId = 'search_' . uniqid();

                $result = [
                    'search_type' => 'multiple_properties',
                    'search_id' => $searchId,
                    'search_name' => $searchName,
                    'extracted_criteria' => $mergedFilters,
                    'raw_llm_response' => $llmResponse,
                    'used_fallback' => true,
                    'filter_count' => count($mergedFilters),
                    'new_filter_count' => $newFilterCount,
                    'updated_filter_count' => $updatedFilterCount,
                    'explanation' => $explanation,
                    'applied_filters_count' => count($appliedFilters),
                ];

                // Add original filters for reference/debugging
                if (!empty($filters)) {
                    $result['new_filters'] = $filters;
                }

                return $result;
            }
        } catch (Exception $e) {
            Log::error('Error extracting search criteria: ' . $e->getMessage());
            $errorDetails = [
                'message' => $e->getMessage(),
            ];

            // Don't include detailed error info in production
            if (app()->environment() !== 'production') {
                $errorDetails['file'] = $e->getFile();
                $errorDetails['line'] = $e->getLine();
                $errorDetails['trace'] = $e->getTraceAsString();
            }

            // Generate explanation for no filters
            $explanation = [
                'matched' => '',
                'unmatched' => 'Query resulted in an error',
            ];

            // Generate a random search ID (for dummy data)
            $searchId = 'search_' . uniqid();
            $searchName = $this->generateDefaultSearchName($prompt, $appliedFilters);

            return [
                'search_type' => 'multiple_properties',
                'search_id' => $searchId,
                'search_name' => $searchName,
                'extracted_criteria' => $appliedFilters, // Return just the applied filters if there's an error
                'error' => $e->getMessage(),
                'error_details' => app()->environment() !== 'production' ? $errorDetails : null,
                'filter_count' => count($appliedFilters),
                'new_filter_count' => 0,
                'updated_filter_count' => 0,
                'explanation' => $explanation,
                'applied_filters_count' => count($appliedFilters),
            ];
        }
    }

    /**
     * Generate system prompt for filter extraction
     *
     * @param array $availableFilters List of available filters
     * @param array $appliedFilters List of applied filters
     * @return string System prompt for LLM
     */
    private function getFilterExtractionSystemPrompt(
        array $availableFilters,
        array $appliedFilters = [],
    ): string {
        $filterDetails = json_encode($availableFilters, JSON_PRETTY_PRINT);
        $schemaDetails = json_encode($this->filterSchema, JSON_PRETTY_PRINT);

        // Create a pretty-printed version of already applied filters
        $appliedFiltersJson = empty($appliedFilters)
            ? 'None'
            : json_encode($appliedFilters, JSON_PRETTY_PRINT);

        return <<<EOT
        You are a specialized AI designed to extract property search filters from natural language queries.
        Your task is to analyze user queries and transform them into structured filter objects.

        AVAILABLE FILTERS:
        $filterDetails

        ALREADY APPLIED FILTERS:
        $appliedFiltersJson

        FILTER SCHEMA:
        $schemaDetails

        FILTER STRUCTURE REQUIREMENTS:
        1. All filters must include: id, type, source_type, label, filterType, value, description, and timestamp
        2. Valid filter types are: "number", "text", "bool", "date", "multiselect"
        3. source_type should be "properties" for property filters

        NUMBER FILTER FORMAT:
        - For ranges: {"type":"number", "filterType":"range", "value":{"min":number, "max":number}}
        - For single comparisons like gt/lt/etc: {"type":"number", "filterType":"lt", "value":{"lt":100000}} - IMPORTANT: value must be an object with filterType as key
        - For equality: {"type":"number", "filterType":"eq", "value":number}

        EXAMPLES OF CORRECT NUMBER FILTERS:
        - Less than $100,000: {"id":1, "type":"number", "source_type":"properties", "label":"Property Value", "filterType":"lt", "value":{"lt":100000}, "description":"..."}
        - Between $200,000 and $500,000: {"id":1, "type":"number", "source_type":"properties", "label":"Property Value", "filterType":"range", "value":{"min":200000, "max":500000}, "description":"..."}
        - Exactly 3 bedrooms: {"id":3, "type":"number", "source_type":"properties", "label":"Bedrooms", "filterType":"eq", "value":3, "description":"..."}

        TEXT FILTER FORMAT:
        - Standard text: {"type":"text", "filterType":"contains|starts_with|ends_with|equals|not_equals|not_contains", "value":{"type":string, "text":string}}
        - Multi-value: {"type":"text", "filterType":"any_of", "value":{"type":"any_of", "values":[strings]}}

        BOOLEAN FILTER FORMAT:
        - {"type":"bool", "value":"yes"|"no"}Okay for multi-select

        MULTISELECT FILTER FORMAT:
        - {"type":"multiselect", "filterType":"contains_any|contains_none", "value":{"values":[{"id":id, "label":string}]}}

        DATE FILTER FORMAT:
        - Range: {"type":"date", "filterType":"date_range", "value":{"start":"YYYY-MM-DD", "end":"YYYY-MM-DD"}}
        - Single: {"type":"date", "filterType":"is_after|is_before|is_equal", "value":{"type":string, "date":"YYYY-MM-DD"}}
        - Relative: {"type":"date", "filterType":"relative_time", "value":{"relativeTime":{"value":number, "unit":"days|weeks|months|years", "direction":"ago|from_now"}}}

        Be thorough and precise in your analysis. Extract all relevant filters, even when they are only implied.
        Use the exact IDs, labels, and structure from the available filters list.

        ADDITIONAL TASKS:
        1. After extracting the filters, provide a structured explanation with two parts:
           a. A brief note on what parts of the query matched to filters (the "matched" section)
           b. A brief note on what parts couldn't be included and why (the "unmatched" section)
        2. Create a concise, descriptive name for this search based on the key filter criteria.
           - This name should be unique and clearly describe what property characteristics the search is looking for
           - It should be brief but descriptive (3-7 words)
           - Examples: "Luxury Beach Homes", "Family-Friendly Suburbs", "Downtown Condos Under 500K"
           - CRITICAL: You MUST analyze the ALREADY APPLIED FILTERS and incorporate their key characteristics into the search name
           - IMPORTANT: The search name should reflect ALL filters that will be applied (both existing and new)
           - For example, if applied filters include "3 bedrooms" and the new query adds "near downtown",
             the name should be something like "3 Bedroom Homes Near Downtown" (not just "Homes Near Downtown")

        HANDLING ALREADY APPLIED FILTERS:
        1. DO NOT create new filters that duplicate any already applied filters
        2. If the user's query mentions updating an already applied filter (e.g., changing price range),
           provide an updated version of that filter in your output
        3. When updating a filter, match its ID and type, but provide the new filterType and value
        4. You MUST consider the content of both applied and new/updated filters when creating the search name
        5. If a user's query mentions criteria that are already filtered, acknowledge this in your explanation
        6. Indicate in your explanation when you're updating an existing filter rather than adding a new one

        SEARCH NAME REQUIREMENTS:
        1. Analyze ALL filters - both already applied and new/updated ones from the current query
        2. Identify the most important/distinctive criteria from all filters (applied and new)
        3. Create a name that describes the COMPLETE search (not just the new part)
        4. If ALREADY APPLIED FILTERS exist, they MUST be represented in the search name
        5. The name should make sense for the entire collection of filters, not just the new ones

        FILTER UPDATE EXAMPLES:
        - If a filter for bedrooms with value 2 exists and user asks for 3 bedrooms, update the existing filter
        - If a price range of $200k-$300k exists and user wants $250k-$350k, update the existing price filter
        - If a neighborhood filter exists and user wants to add another neighborhood, update the multiselect filter

        Both explanation sections should be extremely concise (just a few words or a short phrase each).
        No introductory text or phrases like "Based on your query." Direct and to the point.

        YOUR RESPONSE FORMAT:
        {
          "filters": [...array of filter objects based on schema above...],
          "explanation": {
            "matched": "Brief note on what matched",
            "unmatched": "Brief note on what didn't match"
          },
          "search_name": "Descriptive name for this search"
        }
        EOT;
    }

    /**
     * Extract JSON from LLM response
     *
     * @param string $response LLM response
     * @return array Extracted JSON object
     * @throws InvalidArgumentException If JSON cannot be parsed
     */
    private function extractJsonFromResponse(string $response): array {
        // Try to extract JSON between code blocks
        preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches);
        $jsonString = $matches[1] ?? $response;

        // Clean up the string to ensure it's valid JSON
        $jsonString = trim($jsonString);

        // Decode JSON
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException(
                'Could not parse JSON from LLM response: ' . json_last_error_msg(),
            );
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Expected JSON object or array');
        }

        return $data;
    }

    /**
     * Validate filters against schema
     *
     * @param array $filters Filters to validate
     * @param array $availableFilters List of available filters
     * @param array $appliedFilters List of applied filters
     * @return array Validated filters
     */
    private function validateAndMergeFilters(
        array $filters,
        array $availableFilters,
        array $appliedFilters = [],
    ): array {
        $validatedFilters = [];
        $validFilterIds = array_column($availableFilters, 'id');

        // Track filter IDs and types that are already applied
        $appliedFilterMap = [];
        $usedAppliedFilterKeys = [];

        foreach ($appliedFilters as $filter) {
            if (isset($filter['id'], $filter['filterType'])) {
                $key = $filter['id'] . '_' . $filter['filterType'];
                $appliedFilterMap[$key] = $filter;

                // Also store by just ID to check for updates to the same filter with different filterType
                $idKey = $filter['id'];
                if (!isset($appliedFilterMap['id_' . $idKey])) {
                    $appliedFilterMap['id_' . $idKey] = [];
                }
                $appliedFilterMap['id_' . $idKey][] = $filter;
            }
        }

        foreach ($filters as $filter) {
            // Skip if filter doesn't have required fields
            if (
                !isset(
                    $filter['id'],
                    $filter['type'],
                    $filter['source_type'],
                    $filter['label'],
                    $filter['filterType'],
                    $filter['value'],
                )
            ) {
                continue;
            }

            // Check if filter ID exists in available filters
            if (!in_array($filter['id'], $validFilterIds)) {
                continue;
            }

            // Check filter type is valid
            if (!in_array($filter['type'], ['number', 'text', 'bool', 'date', 'multiselect'])) {
                continue;
            }

            // Check source type is properties
            if ($filter['source_type'] !== 'properties') {
                continue;
            }

            // Generate key for checking if this filter already exists
            $key = $filter['id'] . '_' . $filter['filterType'];
            $idKey = 'id_' . $filter['id'];

            // Check if this is an update to an existing filter
            $isUpdateToExistingFilter = false;

            // Check for exact match (same ID and filterType)
            if (isset($appliedFilterMap[$key])) {
                // This appears to be an update to an existing filter
                $isUpdateToExistingFilter = true;
                $usedAppliedFilterKeys[] = $key;
            }
            // Check for an update that changes the filterType but keeps the same ID
            elseif (isset($appliedFilterMap[$idKey]) && !empty($appliedFilterMap[$idKey])) {
                // This is potentially updating a filter but changing its filterType
                $isUpdateToExistingFilter = true;

                // Mark all filters with this ID as used
                foreach ($appliedFilterMap[$idKey] as $existingFilter) {
                    $existingKey = $existingFilter['id'] . '_' . $existingFilter['filterType'];
                    $usedAppliedFilterKeys[] = $existingKey;
                }
            }

            // Add timestamp if missing
            if (!isset($filter['timestamp'])) {
                $filter['timestamp'] = time();
            }

            // Type-specific validation
            $isValid = true;
            switch ($filter['type']) {
                case 'number':
                    $isValid = $this->validateNumberFilter($filter);
                    break;

                case 'text':
                    $isValid = $this->validateTextFilter($filter);
                    break;

                case 'bool':
                    $isValid = $this->validateBoolFilter($filter);
                    break;

                case 'date':
                    $isValid = $this->validateDateFilter($filter);
                    break;

                case 'multiselect':
                    $isValid = $this->validateMultiselectFilter($filter);
                    break;
            }

            if ($isValid) {
                // Enrich the filter with properties from available filters
                $filter = $this->enrichFilterWithAvailableProperties($filter, $availableFilters);

                // If this is updating an existing filter, copy any additional properties not specified in the update
                if ($isUpdateToExistingFilter && isset($appliedFilterMap[$key])) {
                    $filter = $this->preserveAdditionalProperties($filter, $appliedFilterMap[$key]);
                } elseif ($isUpdateToExistingFilter && isset($appliedFilterMap[$idKey])) {
                    // If we're changing filterType, use the first matching filter by ID
                    $filter = $this->preserveAdditionalProperties(
                        $filter,
                        $appliedFilterMap[$idKey][0],
                    );
                }

                $validatedFilters[] = $filter;
            }
        }

        // Add all applied filters that weren't updated
        foreach ($appliedFilters as $appliedFilter) {
            if (isset($appliedFilter['id'], $appliedFilter['filterType'])) {
                $key = $appliedFilter['id'] . '_' . $appliedFilter['filterType'];

                // Only add this filter if it wasn't updated
                if (!in_array($key, $usedAppliedFilterKeys)) {
                    $validatedFilters[] = $appliedFilter;
                }
            }
        }

        return $validatedFilters;
    }

    /**
     * Preserve additional properties from an existing filter that aren't specified in the update
     *
     * @param array $newFilter The new filter with updated values
     * @param array $existingFilter The existing filter to preserve properties from
     * @return array The merged filter
     */
    private function preserveAdditionalProperties(array $newFilter, array $existingFilter): array {
        // Core properties that are expected to change in an update
        $updatableProperties = ['filterType', 'value', 'timestamp'];

        // Copy all properties from existing filter that aren't in the updatable list
        // and aren't already set in the new filter
        foreach ($existingFilter as $key => $value) {
            if (!in_array($key, $updatableProperties) && !isset($newFilter[$key])) {
                $newFilter[$key] = $value;
            }
        }

        return $newFilter;
    }

    /**
     * Enrich a filter with properties from available filters
     *
     * @param array $filter The filter to enrich
     * @param array $availableFilters List of available filters
     * @return array The enriched filter
     */
    private function enrichFilterWithAvailableProperties(
        array $filter,
        array $availableFilters,
    ): array {
        foreach ($availableFilters as $availableFilter) {
            if ($availableFilter['id'] === $filter['id']) {
                // Properties to copy if they exist in available filter but not in current filter
                $propertiesToCopy = [
                    'description',
                    'options',
                    'subtype',
                    'display_name',
                    'hint',
                    'placeholder',
                    'unit',
                    'allowed_values',
                    'default_value',
                    'group',
                    'icon',
                    'importance',
                ];

                foreach ($propertiesToCopy as $property) {
                    if (isset($availableFilter[$property]) && !isset($filter[$property])) {
                        $filter[$property] = $availableFilter[$property];
                    }
                }

                // Special handling for multiselect options to ensure we have full option details
                if (
                    $filter['type'] === 'multiselect' &&
                    isset($filter['value']['values']) &&
                    isset($availableFilter['options']) &&
                    is_array($availableFilter['options'])
                ) {
                    $optionsMap = [];
                    foreach ($availableFilter['options'] as $option) {
                        if (isset($option['id'])) {
                            $optionsMap[$option['id']] = $option;
                        }
                    }

                    // Enhance each value with complete option details if possible
                    foreach ($filter['value']['values'] as $key => $value) {
                        if (isset($value['id']) && isset($optionsMap[$value['id']])) {
                            // Preserve the original label if it exists
                            $originalLabel = $value['label'] ?? null;
                            $filter['value']['values'][$key] = $optionsMap[$value['id']];

                            // Keep original label if it exists and differs from option label
                            if (
                                $originalLabel &&
                                (!isset($optionsMap[$value['id']]['label']) ||
                                    $originalLabel !== $optionsMap[$value['id']]['label'])
                            ) {
                                $filter['value']['values'][$key]['label'] = $originalLabel;
                            }
                        }
                    }
                }

                break;
            }
        }

        return $filter;
    }

    /**
     * Validate number filter
     *
     * @param array $filter Number filter to validate
     * @return bool Whether filter is valid
     */
    private function validateNumberFilter(array &$filter): bool {
        // Check filterType is valid for number
        $validFilterTypes = ['range', 'gt', 'gte', 'lt', 'lte', 'eq', 'neq'];
        if (!in_array($filter['filterType'], $validFilterTypes)) {
            return false;
        }

        if ($filter['filterType'] === 'range') {
            // Validate range filter
            if (
                !isset($filter['value']['min'], $filter['value']['max']) ||
                !is_numeric($filter['value']['min']) ||
                !is_numeric($filter['value']['max'])
            ) {
                return false;
            }

            // Ensure min <= max
            if ($filter['value']['min'] > $filter['value']['max']) {
                $temp = $filter['value']['min'];
                $filter['value']['min'] = $filter['value']['max'];
                $filter['value']['max'] = $temp;
            }
        } elseif ($filter['filterType'] === 'eq') {
            // Validate equality filter
            if (!is_numeric($filter['value'])) {
                return false;
            }
        } else {
            // Try to fix incorrectly formatted comparison filters
            if (is_numeric($filter['value']) && !isset($filter['value'][$filter['filterType']])) {
                // Auto-fix: convert direct numeric value to proper structure
                $numericValue = $filter['value'];
                $filter['value'] = [$filter['filterType'] => $numericValue];
                Log::info("Auto-fixed number filter format for {$filter['label']} filter");
            }

            // Validate single comparison filter
            if (
                !isset($filter['value'][$filter['filterType']]) ||
                !is_numeric($filter['value'][$filter['filterType']])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate text filter
     *
     * @param array $filter Text filter to validate
     * @return bool Whether filter is valid
     */
    private function validateTextFilter(array $filter): bool {
        $validFilterTypes = [
            'contains',
            'starts_with',
            'ends_with',
            'equals',
            'not_equals',
            'not_contains',
            'any_of',
        ];
        if (!in_array($filter['filterType'], $validFilterTypes)) {
            return false;
        }

        if ($filter['filterType'] === 'any_of') {
            // Validate multi-value filter
            if (
                !isset($filter['value']['type'], $filter['value']['values']) ||
                $filter['value']['type'] !== 'any_of' ||
                !is_array($filter['value']['values'])
            ) {
                return false;
            }

            // Each value must be a string
            foreach ($filter['value']['values'] as $value) {
                if (!is_string($value)) {
                    return false;
                }
            }
        } else {
            // Validate standard text filter
            if (
                !isset($filter['value']['type'], $filter['value']['text']) ||
                $filter['value']['type'] !== $filter['filterType'] ||
                !is_string($filter['value']['text'])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate boolean filter
     *
     * @param array $filter Boolean filter to validate
     * @return bool Whether filter is valid
     */
    private function validateBoolFilter(array &$filter): bool {
        // Convert true/false to yes/no if needed
        if (isset($filter['value'])) {
            if ($filter['value'] === true || $filter['value'] === 'true') {
                $filter['value'] = 'yes';
            } elseif ($filter['value'] === false || $filter['value'] === 'false') {
                $filter['value'] = 'no';
            }
        }

        return isset($filter['value']) && in_array($filter['value'], ['yes', 'no']);
    }

    /**
     * Validate date filter
     *
     * @param array $filter Date filter to validate
     * @return bool Whether filter is valid
     */
    private function validateDateFilter(array $filter): bool {
        $validFilterTypes = ['date_range', 'is_after', 'is_before', 'is_equal', 'relative_time'];
        if (!in_array($filter['filterType'], $validFilterTypes)) {
            return false;
        }

        if ($filter['filterType'] === 'date_range') {
            // Validate date range filter
            if (
                !isset($filter['value']['start'], $filter['value']['end']) ||
                !$this->isValidDate($filter['value']['start']) ||
                !$this->isValidDate($filter['value']['end'])
            ) {
                return false;
            }
        } elseif ($filter['filterType'] === 'relative_time') {
            // Validate relative time filter
            if (
                !isset(
                    $filter['value']['relativeTime'],
                    $filter['value']['relativeTime']['value'],
                    $filter['value']['relativeTime']['unit'],
                    $filter['value']['relativeTime']['direction'],
                ) ||
                !is_numeric($filter['value']['relativeTime']['value']) ||
                !in_array($filter['value']['relativeTime']['unit'], [
                    'days',
                    'weeks',
                    'months',
                    'years',
                ]) ||
                !in_array($filter['value']['relativeTime']['direction'], ['ago', 'from_now'])
            ) {
                return false;
            }
        } else {
            // Validate single date comparison
            if (
                !isset($filter['value']['type'], $filter['value']['date']) ||
                $filter['value']['type'] !== $filter['filterType'] ||
                !$this->isValidDate($filter['value']['date'])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate multiselect filter
     *
     * @param array $filter Multiselect filter to validate
     * @return bool Whether filter is valid
     */
    private function validateMultiselectFilter(array $filter): bool {
        $validFilterTypes = ['contains_any', 'contains_none'];
        if (!in_array($filter['filterType'], $validFilterTypes)) {
            return false;
        }

        if (!isset($filter['value']['values']) || !is_array($filter['value']['values'])) {
            return false;
        }

        // Each value must have id and label
        foreach ($filter['value']['values'] as $value) {
            if (!isset($value['id'], $value['label'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a string is a valid date in YYYY-MM-DD format
     *
     * @param string $date Date string to validate
     * @return bool Whether date is valid
     */
    private function isValidDate(string $date): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    /**
     * Generate a default search name based on the prompt
     *
     * @param string $prompt The user's search prompt
     * @param array $appliedFilters List of applied filters
     * @return string A default search name
     */
    private function generateDefaultSearchName(string $prompt, array $appliedFilters = []): string {
        // Truncate prompt if too long
        $truncatedPrompt = substr($prompt, 0, 30);
        if (strlen($prompt) > 30) {
            $truncatedPrompt .= '...';
        }

        // If we have applied filters, add a count to the name
        $prefix = 'Property Search';
        if (!empty($appliedFilters)) {
            $prefix = 'Property Search (' . count($appliedFilters) . ' filters)';
        }

        return $prefix . ': ' . $truncatedPrompt;
    }
}
