<?php

namespace Model;

use App;

class Claim extends AModel implements IModel {

    public function all() {
        
    }

    public function get($id) {
        return App::$DATABASE->get('claim', ['claim_id', 'claim_user_id', 'claim_price', 'claim_date', 'claim_currency'], ['claim_id' => $id]);
    }

    public function get_by_user_id($id) {
        return App::$DATABASE->select('claim', ['claim_id', 'claim_user_id', 'claim_price', 'claim_date', 'claim_currency'], ['claim_user_id' => $id, "ORDER" => ['claim_date' => 'DESC']]);
    }

    public function remove($id) {
        
    }

    public function save($element) {
        App::$DATABASE->insert('claim', $element);
    }

}
