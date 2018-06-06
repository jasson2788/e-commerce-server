<?php

namespace Model;

use App;

class Stats extends AModel implements IModel {

    public function all() {
        
    }

    public function get($id) {
        
    }

    public function remove($id) {
        
    }

    public function save($element) {
        $stats_ids = ["all" => "-", "year" => date("Y"), "month" => date("Y-m"), "day" => date("Y-m-d")];
        $exists_ids = [];
        $stats = App::$DATABASE->select("stats", "stats_id", ["stats_id" =>
            [$stats_ids["all"], $stats_ids["year"], $stats_ids["month"], $stats_ids["day"]]
        ]);

        for ($i = 0; $i < sizeof($stats); $i++) {
            $exists_ids[$stats[$i]] = true;
        }

        $update = [];
        $insert = [];
        foreach ($stats_ids as $key => $value) {
            if (array_key_exists($value, $exists_ids)) {
                array_push($update, $value);
            } else {
                array_push($insert, $value);
            }
        }

        if (sizeof($update) > 0) {
            $update_element = $element;
            foreach ($update_element as $key => $value) {
                $update_element[$key . '[+]'] = $value;
                unset($update_element[$key]);
            }

            App::$DATABASE->update("stats", $update_element, ["stats_id" => $update]);
        }

        if (sizeof($insert) > 0) {
            $insert_elements = [];
            for ($i = 0; $i < sizeof($insert); $i++) {
                $element["stats_id"] = $insert[$i];
                array_push($insert_elements, $element);
            }

            App::$DATABASE->insert("stats", $insert_elements);
        }
    }

}
