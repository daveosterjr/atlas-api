<?php

namespace App\Config;

class AllFilters {
    public static function getFilters() {
        // Combine all filters from different sources
        return array_merge(PropertyFilters::getFilters(), ContactFilters::getFilters());
    }

    public static function getPropertyFilters() {
        return PropertyFilters::getFilters();
    }

    public static function getContactFilters() {
        return ContactFilters::getFilters();
    }
}
