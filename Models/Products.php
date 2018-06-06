<?php

namespace Model;

use App;

class Products extends AModel implements IModel {

    const SHIPPING = false;

    public function all() {
        return App::$DATABASE->select('products', [
                    "products_id", "products_title", "products_price", "products_src", "products_price_discount", "products_sold_out", "products_small",
                    "products_medium", "products_large", "products_xlarge", "products_x2large", "products_x3large", "products_x4large", "products_x5large"
                        ], ["ORDER" => ["products_id" => "ASC"], "products_show" => 1]);
    }

    public function get($id) {
        return App::$DATABASE->get('products', [
                    "products_id", "products_title", "products_price", "products_price_discount", "products_src", "products_src_angle", "products_src_back",
                    "products_desc", "products_sold_out", "products_type", "products_small", "products_medium",
                    "products_large", "products_xlarge", "products_x2large", "products_x3large",
                    "products_x4large", "products_x5large"
                        ], ["AND" => ["products_id" => $id, "products_show" => 1]]);
    }

    public function order($currency_info, $coupon, $shipping_address) {
        $cart = json_decode(filter_input(INPUT_COOKIE, 'cart'));
        $order = (object) array('currency' => $currency_info, 'items' => array(), 'subtotal' => 0, 'max' => ["p" => 0], 'discount' => 0, 'discount_code' => '', 'total' => 0, 'shipping' => array(), 'shipping_price' => array('USA' => 0, 'Canada' => 0, 'International' => 0), 'shipping_address' => $shipping_address, 'totalwithshipping' => 0);

        foreach ($cart as $id => $element) {
            foreach ($element as $size => $qty) {
                $s = $this->get_field_from_size($size);
                if (!isset($s)) {
                    continue;
                }
                $this->get_order_item($id, $s, $size, $qty, $order);
            }
        }
        if (isset($coupon) && isset($coupon['coupon_discount'])) {
            $type = (int) $coupon['coupon_type'] === 0 ? $order->subtotal : $order->max["p"];
            $order->discount = round($type * 100 * ($coupon['coupon_discount'] / 100) / 100, 2);
            $order->discount_code = $coupon['coupon_id'];
            $order->total = $order->subtotal - $order->discount;
        } else {
            $order->total = $order->subtotal;
        }
        
        if (Products::SHIPPING === false) {
            unset($order->shipping_price);
            unset($order->totalwithshipping);
            
            $order->shipping = false;
        }

        return $this->get_total_with_shipping($order);
    }

    private function get_shipping(&$order) {
        $shipping = Store::shipping();
        $order->shipping_zone = filter_input(INPUT_COOKIE, 'shipping_zone');
        if ($order->shipping_zone !== 'USA' && $order->shipping_zone !== 'Canada' && $order->shipping_zone !== 'International') {
            $order->shipping_zone = 'International';
        }


        $order->shipping_price['USA'] += $shipping[$order->max["t"]]['USA']['first_item_price'];
        $order->shipping_price['Canada'] += $shipping[$order->max["t"]]['Canada']['first_item_price'];
        $order->shipping_price['International'] += $shipping[$order->max["t"]]['International']['first_item_price'];
        $order->shipping[$order->max["t"]] --;

        foreach ($order->shipping as $key => $value) {
            if ($value > 0) {
                $order->shipping_price['USA'] += $shipping[$key]['USA']['additional_item_price'] * $value;
                $order->shipping_price['Canada'] += $shipping[$key]['Canada']['additional_item_price'] * $value;
                $order->shipping_price['International'] += $shipping[$key]['International']['additional_item_price'] * $value;
            }
        }

        $order->shipping_price['USA'] = round($order->shipping_price['USA'] * 100 * $order->currency["rate"] / 100, 2);
        $order->shipping_price['Canada'] = round($order->shipping_price['Canada'] * 100 * $order->currency["rate"] / 100, 2);
        $order->shipping_price['International'] = round($order->shipping_price['International'] * 100 * $order->currency["rate"] / 100, 2);
    }

    private function get_total_with_shipping(&$order) {
        if (Products::SHIPPING === true) {
            $this->get_shipping($order);
            $order->totalwithshipping = array(
                'USA' => $order->shipping_price['USA'] + $order->total,
                'Canada' => $order->shipping_price['Canada'] + $order->total,
                'International' => $order->shipping_price['International'] + $order->total);
        }
        return $order;
    }

    private function get_order_item($id, $size_field, $size, $qty, &$order) {
        $re = App::$DATABASE->get('products', [
            "products_title", "products_price", "products_price_discount", "products_type", "products_id"
                ], ["AND" => ["products_id" => $id, "products_show" => 1, "products_sold_out" => 0, $size_field => 1]]);
        if ($re) {
            $price = round((isset($re["products_price_discount"]) ? $re["products_price_discount"] : $re["products_price"]) * 100 * $order->currency["rate"] / 100, 2);
            if ($order->max["p"] < $price) {
                $order->max["p"] = $price;
                $order->max["t"] = $re['products_type'];
            }

            if (!array_key_exists($re['products_type'], $order->shipping)) {
                $order->shipping[$re['products_type']] = $qty;
            } else {
                $order->shipping[$re['products_type']] += $qty;
            }

            array_push($order->items, ["title" => $re['products_title'], "price" => $price, "quantity" => $qty, "total_price" => $price * $qty, "size" => $size, "id" => $re['products_id']]);
            $order->subtotal += $price * $qty;
        }
    }

    public function cart() {
        $cart = json_decode(filter_input(INPUT_COOKIE, 'cart'));
        if (!isset($cart)) {
            return array();
        }
        $return = (object) ["cart" => array(), "not_avalaible" => 0, "shipping" => Products::SHIPPING !== false ? Store::shipping() : false];
        foreach ($cart as $id => $element) {
            foreach ($element as $size => $qty) {
                $size_field = $this->get_field_from_size($size);
                if (!isset($size_field)) {
                    continue;
                }
                $this->get_return_cart_element($return, $cart, $qty, $size, $size_field, $id);
            }
        }
        $return->cookie_cart = $cart;
        return $return;
    }

    private function get_return_cart_element(&$return, &$cart, $qty, $size, $size_field, $id) {
        $element = App::$DATABASE->get('products', [
            "products_id", "products_title", "products_price", "products_price_discount", "products_src", "products_type"
                ], ["AND" => ["products_id" => $id, "products_show" => 1, "products_sold_out" => 0, $size_field => 1]]);

        if ($element) {
            $element["products_qty"] = $qty;
            $element["products_size"] = $size;
            array_push($return->cart, $element);
        } else {
            $return->not_avalaible++;
            if (count(get_object_vars($cart->$id)) > 1) {
                unset($cart->$id->$size);
            } else {
                unset($cart->$id);
            }
        }
    }

    private function get_field_from_size($size) {
        switch ($size) {
            case "small":
                return "products_small";
            case "medium":
                return "products_medium";
            case "large":
                return "products_large";
            case "x-large":
                return "products_xlarge";
            case "x2-large":
                return "products_x2large";
            case "x3-large":
                return "products_x3large";
            case "x4-large":
                return "products_x4large";
            case "x5-large":
                return "products_x5large";
            default:
                return null;
        }
    }

    public function remove($id) {
        
    }

    public function save($element) {
        
    }

}
