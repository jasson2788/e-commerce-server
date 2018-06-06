<?php

namespace Model;

use App;
use stdClass;

abstract class AModel {

    public function __construct() {
        App::initDatabase();
    }

    public function get_changed_properties($element, $skip = []) {
        $model = new stdClass();
        foreach ($element as $prop => $value) {
            if (isset($value->changed) && $value->changed === true && !array_key_exists($prop, $skip)) {
                $model->$prop = $this->sanitize_output($value->value);
            }
        }
        return json_decode(json_encode($model), true);
    }

    private function sanitize_output($buffer) {
        return preg_replace(array('/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s'), array('>', '<', '\\1'), $buffer);
    }
    
    public static function get_accepted_properties($array) {
        $return = array();  
        for($i = 0; $i < count($array); $i++) {
            $return[$array[$i]] = 0;
        }
        return $return;
    }

}

interface IModel {

    public function get($id);

    public function all();

    public function save($element);

    public function remove($id);
}
