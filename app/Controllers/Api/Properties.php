<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Properties extends ResourceController {
    public function getProperty($id = null) {
        // Dummy data - in real app this would come from a database
        $dummyProperties = [
            1 => [
                'id' => 1,
                'address' => '5519 Goethe Ave',
                'city' => 'Saint Louis',
                'state' => 'MO',
                'zip' => '63109',
            ],
            2 => [
                'id' => 2,
                'address' => '1170 Hampton Park Dr',
                'city' => 'Saint Louis',
                'state' => 'MO',
                'zip' => '63117',
            ],
        ];

        return $this->response->setJSON($dummyProperties[array_rand($dummyProperties)]);
    }
}
