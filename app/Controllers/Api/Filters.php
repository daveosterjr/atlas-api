<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Filters extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        // Defining filters array
        $filters = [
            [
                "id" => 1,
                "label" => "Property Value",
                "type" => "number",
                "subtype" => "money",
                "source_type" => "properties",
                "description" => "The estimated value of the property in dollars",
                "aliases" => [
                    "home value",
                    "house value",
                    "price",
                    "worth",
                    "market value",
                ],
                "min" => 0,
                "max" => 1000000,
            ],
            [
                "id" => 2,
                "label" => "Year Built",
                "type" => "number",
                "subtype" => "year",
                "source_type" => "properties",
                "description" => "The year when the property was constructed",
                "aliases" => ["construction year", "build date", "built in"],
                "min" => 1900,
                "max" => 2023,
            ],
            [
                "id" => 3,
                "label" => "Acquisition Date",
                "type" => "date",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The date when the property was acquired",
                "aliases" => [
                    "purchase date",
                    "bought on",
                    "date acquired",
                    "closing date",
                ],
            ],
            [
                "id" => 4,
                "label" => "Owner Name",
                "type" => "text",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The legal name of the property owner",
                "aliases" => ["property owner", "homeowner", "landlord"],
            ],
            [
                "id" => 5,
                "label" => "Bedrooms",
                "type" => "number",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Number of bedrooms in the property",
                "aliases" => ["beds", "BR", "bedroom count"],
                "min" => 0,
                "max" => 10,
            ],
            [
                "id" => 6,
                "label" => "Bathrooms",
                "type" => "number",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Number of bathrooms in the property",
                "aliases" => ["baths", "BA", "bathroom count"],
                "min" => 0,
                "max" => 10,
            ],
            [
                "id" => 7,
                "label" => "Last Contact Date",
                "type" => "date",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The most recent date when the owner was contacted",
                "aliases" => ["contacted on", "last reached", "communication date"],
            ],
            [
                "id" => 8,
                "label" => "Property Address",
                "type" => "text",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The physical address of the property",
                "aliases" => ["address", "location", "street address", "property location"],
            ],
            [
                "id" => 9,
                "label" => "Lot Size",
                "type" => "number",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The total area of the property lot in square feet",
                "aliases" => ["land size", "acreage", "square footage", "property size"],
                "min" => 0,
                "max" => 100000,
            ],
            [
                "id" => 10,
                "label" => "Last Modified Date",
                "type" => "date",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Date when the property record was last updated",
                "aliases" => ["updated on", "last updated", "modification date"],
            ],
            [
                "id" => 11,
                "label" => "Equity Percentage",
                "type" => "number",
                "subtype" => "percent",
                "source_type" => "properties",
                "description" => "The percentage of equity the owner has in the property",
                "aliases" => ["equity %", "ownership percentage", "ownership stake"],
                "min" => 0,
                "max" => 100,
            ],
            [
                "id" => 12,
                "label" => "Vacant",
                "type" => "bool",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Whether the property is currently vacant/unoccupied",
                "aliases" => ["empty", "unoccupied", "not occupied", "vacancy"],
            ],
            [
                "id" => 13,
                "label" => "In Preforeclosure",
                "type" => "bool",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Whether the property is in preforeclosure status",
                "aliases" => [
                    "preforeclosure",
                    "pre-foreclosure",
                    "pre-foreclosure status",
                    "bank owned",
                ],
            ],
            [
                "id" => 14,
                "label" => "Has Pool",
                "type" => "bool",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Whether the property has a swimming pool",
                "aliases" => ["pool", "swimming pool", "water feature"],
            ],
            [
                "id" => 15,
                "label" => "Property Type",
                "type" => "multiselect",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The category or classification of the property",
                "aliases" => ["property category", "home type", "building type"],
                "options" => [
                    [
                        "id" => 1,
                        "label" => "Single Family",
                    ],
                    [
                        "id" => 2,
                        "label" => "Multi-Family",
                    ],
                    [
                        "id" => 3,
                        "label" => "Condominium",
                    ],
                    [
                        "id" => 4,
                        "label" => "Townhouse",
                    ],
                    [
                        "id" => 5,
                        "label" => "Commercial",
                    ],
                    [
                        "id" => 6,
                        "label" => "Vacant Land",
                    ],
                ],
            ],
            [
                "id" => 16,
                "label" => "Property Condition",
                "type" => "multiselect",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "The overall condition of the property",
                "aliases" => ["condition", "state", "home condition"],
                "options" => [
                    [
                        "id" => 1,
                        "label" => "Excellent",
                    ],
                    [
                        "id" => 2,
                        "label" => "Good",
                    ],
                    [
                        "id" => 3,
                        "label" => "Fair",
                    ],
                    [
                        "id" => 4,
                        "label" => "Poor",
                    ],
                    [
                        "id" => 5,
                        "label" => "Distressed",
                    ],
                ],
            ],
            [
                "id" => 17,
                "label" => "Listing Status",
                "type" => "multiselect",
                "subtype" => null,
                "source_type" => "properties",
                "description" => "Current status of the property in the sales process",
                "aliases" => ["status", "sale status", "property status"],
                "options" => [
                    [
                        "id" => 1,
                        "label" => "For Sale",
                    ],
                    [
                        "id" => 2,
                        "label" => "Pending",
                    ],
                    [
                        "id" => 3,
                        "label" => "Sold",
                    ],
                    [
                        "id" => 4,
                        "label" => "Off Market",
                    ],
                    [
                        "id" => 5,
                        "label" => "Foreclosure",
                    ],
                ],
            ],
            [
                "id" => 18,
                "label" => "Age",
                "type" => "number",
                "subtype" => null,
                "source_type" => "contacts",
                "description" => "Age of the contact in years",
                "aliases" => ["age", "years old", "person age"],
                "min" => 18,
                "max" => 100,
            ],
        ];

        // Create response structure
        $response = [
            "status" => "success",
            "code" => 200,
            "message" => "Filters retrieved successfully",
            "data" => [
                "filters" => $filters,
            ],
            "meta" => [
                "timestamp" => time(),
                "version" => "1.0",
            ],
        ];

        return $this->respond($response);
    }
} 