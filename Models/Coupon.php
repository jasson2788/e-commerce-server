<?php

namespace Model;

use App;
use Model\User;

class Coupon extends AModel implements IModel {

    public static $MIN_CLAIM = 5;

    public function all() {
        
    }

    public function get($id) {
        if (!isset($id)) {
            return;
        }

        $where = ["AND" => ["coupon_id" => $id, "coupon_valid" => 1]];
        $name = filter_input(INPUT_COOKIE, 'name');
        if (isset($name) && !empty($name)) {
            $user = new User();
            $where["AND"]["coupon_user_id[!]"] = $user->get_current_user_id();
        }
        return App::$DATABASE->get('coupon', ["coupon_id", "coupon_discount", "coupon_type"], $where);
    }

    public function get_user_id($coupon) {
        return App::$DATABASE->get('coupon', "coupon_user_id", ["coupon_id" => $coupon]);
    }

    public function get_by_user_id($id, $admin) {
        $fields = ["coupon_id", "coupon_profit_total", "coupon_use", "coupon_payed"];
        if ($admin === true) {
            array_push($fields, "coupon_disabled");
        }
        $coupon = App::$DATABASE->get('coupon', $fields, ["coupon_user_id" => $id]);
        $coupon['coupon_profit_total'] *= 0.95;
        $coupon['coupon_payed'] *= 0.95;
        return $coupon;
    }

    public function verify() {
        $c = filter_input(INPUT_COOKIE, 'discount');
        $json = json_decode($c);
        if (isset($json) && isset($json->coupon_id)) {
            return $this->get($json->coupon_id);
        }
    }

    public function remove($id) {
        
    }

    public function save($element) {
        
    }

    public function update($id, $subtotal) {
        $perc = App::$DATABASE->get('coupon', 'coupon_profit', ["coupon_id" => $id]);
        $total = 0;
        if ($perc) {
            $total = round($perc / 100 * $subtotal, 2);
            App::$DATABASE->update('coupon', ["coupon_use[+]" => "1", "coupon_profit_total[+]" => $total], ["coupon_id" => $id]);
        }
        return $total;
    }

    public function update_price($id, $price) {
        App::$DATABASE->update('coupon', ["coupon_payed[+]" => $price], ["coupon_id" => $id]);
    }

    public function disable($id) {
        App::$DATABASE->update('coupon', ["coupon_disabled" => 1], ["coupon_id" => $id]);
    }

    public function enable($id) {
        App::$DATABASE->update('coupon', ["coupon_disabled" => 0], ["coupon_id" => $id]);
    }

}
