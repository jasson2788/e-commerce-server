<?php

namespace Model;

use App;

class ContactUs extends AModel implements IModel {

    public function all() {
        
    }

    public function get($id) {
        
    }

    public function remove($id) {
        
    }

    public function save($element) {
        return App::$DATABASE->insert('contact_us', $element);
    }

}
