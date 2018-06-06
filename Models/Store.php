<?php

namespace Model;

use App;

class Store {

    public static function shipping() {
        return [
            "t-shirt" => [
                "USA" => ["first_item_price" => 11.00, "additional_item_price" => 2],
                "Canada" => ["first_item_price" => 7.3, "additional_item_price" => 2],
                "International" => ["first_item_price" => 15.00, "additional_item_price" => 3]
            ],
            "sweatshirt" => [
                "USA" => ["first_item_price" => 12.00, "additional_item_price" => 2],
                "Canada" => ["first_item_price" => 7.3, "additional_item_price" => 2],
                "International" => ["first_item_price" => 15.00, "additional_item_price" => 3]
            ],
            "snapback" => [
                "USA" => ["first_item_price" => 6.50, "additional_item_price" => 1.50],
                "Canada" => ["first_item_price" => 8.00, "additional_item_price" => 2.00],
                "International" => ["first_item_price" => 15.00, "additional_item_price" => 2.00]
            ]
        ];
    }

    public static function get_shipping_zone_from_country($country) {
        if ($country === 'US') {
            return "USA";
        } else if ($country === 'CA') {
            return "Canada";
        } else {
            return "International";
        }
    }

    public static function sizes() {
        return [
            "small" => "1",
            "x-small" => "2",
            "medium" => "3",
            "large" => "4",
            "x-large" => "5",
            "2x-large" => "6",
            "3x-large" => "7",
            "4x-large" => "8",
            "5x-large" => "9"
        ];
    }
}
