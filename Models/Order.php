<?php

namespace Model;

use App;

class Order extends AModel implements IModel {

    public function all() {
        
    }

    public function get($id, $admin = null) {
        $fields = ["order_id", "order_date", "order_processing", "order_total", "order_currency", "order_total", "order_shipping", "order_subtotal",
            "order_country", "order_state", "order_city", "order_postal_code", "order_line1", "order_line2"];
        if ($admin) {
            $fields = ["order_id", "order_payer_id", "order_processing"];
        }
        return App::$DATABASE->get("order", $fields, ["order_id" => $id]);
    }

    public function get_by_username($user_id, $id = null, $admin = null) {
        if (!isset($id) || empty($id)) {
            return App::$DATABASE->select("order", ["order_id", "order_date", "order_processing", "order_total", "order_currency", "order_total", "order_shipping", "order_subtotal",
                        "order_country", "order_state", "order_city", "order_postal_code", "order_line1", "order_line2"], ["ORDER" => ["order_date" => "DESC"], "order_user_id" => $user_id]);
        } else {
            $fields = ["order_id", "order_date", "order_processing", "order_total", "order_currency", "order_total", "order_shipping", "order_subtotal",
                "order_country", "order_state", "order_city", "order_postal_code", "order_line1", "order_line2"];
            if ($admin) {
                $fields = ["order_id", "order_payer_id", "order_processing"];
            }
            return App::$DATABASE->get("order", $fields, ["AND" => ["order_id" => $id, "order_user_id" => $user_id]]);
        }
    }

    public function get_order_items_by($user_id, $id) {
        return App::$DATABASE->select("order_item", ["[>]order" => ["order_item_order_id" => "order_id"], "[>]products" => ["order_item_products_id" => "products_id"]], ["order_item_size", "order_item_quantity", "products_title", "products_src", "products_src_back"], ["AND" => ["order_item_order_id" => $id, "order_user_id" => $user_id]]);
    }

    public function remove($id) {
        
    }

    public function save($element) {
        App::$DATABASE->insert("order", $element);
    }

    public function update_processing($id, $processing) {
        App::$DATABASE->update("order", ["order_processing" => $processing], ["order_id" => $id]);
    }

    public function update($fields, $id) {
        App::$DATABASE->update("order", $fields, ["order_id" => $id]);
    }

}
