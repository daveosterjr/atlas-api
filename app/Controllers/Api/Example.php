<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Example extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        return $this->respond([
            'status' => 'success',
            'message' => 'Welcome to Atlas API',
            'data' => [
                'version' => '1.0.0',
                'author' => 'Dave Oster'
            ]
        ]);
    }
}