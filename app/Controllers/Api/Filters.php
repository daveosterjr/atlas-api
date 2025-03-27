<?php

namespace App\Controllers\Api;

use App\Config\AllFilters;
use CodeIgniter\RESTful\ResourceController;

class Filters extends ResourceController {
    protected $format = 'json';

    public function index() {
        // Get all filters from the combined class
        $filters = AllFilters::getFilters();

        // Create response structure
        $response = [
            'status' => 'success',
            'code' => 200,
            'message' => 'Filters retrieved successfully',
            'data' => [
                'filters' => $filters,
            ],
            'meta' => [
                'timestamp' => time(),
                'version' => '1.0',
            ],
        ];

        return $this->respond($response);
    }

    public function properties() {
        // Get only property filters
        $filters = AllFilters::getPropertyFilters();

        $response = [
            'status' => 'success',
            'code' => 200,
            'message' => 'Property filters retrieved successfully',
            'data' => [
                'filters' => $filters,
            ],
            'meta' => [
                'timestamp' => time(),
                'version' => '1.0',
            ],
        ];

        return $this->respond($response);
    }

    public function contacts() {
        // Get only contact filters
        $filters = AllFilters::getContactFilters();

        $response = [
            'status' => 'success',
            'code' => 200,
            'message' => 'Contact filters retrieved successfully',
            'data' => [
                'filters' => $filters,
            ],
            'meta' => [
                'timestamp' => time(),
                'version' => '1.0',
            ],
        ];

        return $this->respond($response);
    }
}
