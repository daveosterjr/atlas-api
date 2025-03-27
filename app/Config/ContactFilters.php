<?php

namespace App\Config;

class ContactFilters {
    public static function getFilters() {
        return [
            [
                'id' => 18,
                'label' => 'Age',
                'type' => 'number',
                'subtype' => null,
                'source_type' => 'contacts',
                'description' => 'Age of the contact in years',
                'aliases' => ['age', 'years old', 'person age'],
                'min' => 18,
                'max' => 100,
            ],
            // Add more contact filters as needed here
        ];
    }
}
