<?php

namespace Model;

use App;

class OrderItem extends AModel implements IModel {

    public function all() {
        
    }

    public function get($id) {
        
    }
    

    public function remove($id) {
        
    }

    public function save($element) {
        App::$DATABASE->insert("order_item", $element);
    }

}
