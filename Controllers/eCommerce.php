<?php

namespace Controller;

use App;
use Model\User;
use Model\Payment;
use Model\Currency;
use Model\Coupon;
use Model\Products;
use Model\Order;
use Model\OrderItem;
use Model\Stats;
use Model\Store;
use Model\ContactUs;
use Model\Claim;
use utils\Validate;
use utils\UTILS;

class eCommerce {
    
    /** ==============================================================================================================================
     * Sends the order to PAYPAL
     */
    public function checkout() {
        $order = $this->price(true);
        $user = new User();

        if (count($order->items) === 0) {
            App::response(400, "NO ITEMS");
        }

        App::response(200, (new Payment())->order($order, $user->get_current_user_id(false)));
    }

    /** ==============================================================================================================================
     * Returns final price before checkout on PAYPAL
     */
    public function before_checkout() {
        App::response(200, $this->price(true));
    }

    /** ==============================================================================================================================
     * Returns cart information (items, prices, address)
     */
    private function price($with_shipping = null, $new_coupon = null) {
        $currency_info = Currency::get();

        $coupon_cookie = !isset($new_coupon) ? json_decode(filter_input(INPUT_COOKIE, 'discount')) : (object) $new_coupon;
        if (isset($coupon_cookie->coupon_id)) {
            $coupon_model = new Coupon();
            $coupon = $coupon_model->get($coupon_cookie->coupon_id);
        }

        if (!isset($with_shipping)) {
            return (new Products())->order($currency_info, $coupon);
        } else {
            $shipping = json_decode(filter_input(INPUT_GET, 'shipping'));
            $shipping_address = (new User())->shipping_address($shipping);
            return (new Products())->order($currency_info, $coupon, $shipping_address);
        }
    }

    /** ==============================================================================================================================
     * Verifies the PAYPAL order
     */
    public function process() {
        $id = filter_input(INPUT_GET, 'processing_id');
        if (!isset($id) || empty($id)) {
            App::response(400, 'NO PROCESSING_ID');
        }

        $order_model = new Order();
        if (!($order = $order_model->get($id, true)) || $order['order_processing']) {
            App::response(400, 'ERROR');
        }

        $order_model->update_processing($id, 1);

        $payment_model = new Payment();
        $result = $payment_model->verify($order['order_id'], $order['order_payer_id'], $order_model);
        $this->process_items($result);

        echo App::response(200, $order_model->get($id));
    }

    public function order() {
        $id = filter_input(INPUT_GET, 'id');
        if (!isset($id) || empty($id)) {
            App::response(400, 'NO ORDER_ID');
        }

        $order = new Order();
        $result = $order->get('PAY-' . $id);
        App::response($result ? 200 : 400, $result ? $result : 'NOT_FOUND');
    }

    /** ==============================================================================================================================
     * Process the items returned by PAYPAL
     */
    private function process_items($result) {
        $orderitem_model = new OrderItem();
        $coupon = null;
        $coupon_price = null;
        $coupon_discount = 0;

        foreach ($result['items'] as $value) {
            $name = $value->getName();
            if (App::starts_with($name, 'code:')) {
                $coupon = trim(substr($name, 6, strlen($name)));
                $coupon_price = $value->getPrice();
            } else {
                $parts = explode("|", $value->getSku());
                $orderitem_model->save(["order_item_order_id" => $result['id'], "order_item_products_id" => $parts[0], "order_item_quantity" => $value->getQuantity(), "order_item_size" => $parts[1]]);
            }
        }

        $rate = Currency::get_default_rate($result['currency']);
        if (isset($coupon)) {
            $coupon_model = new Coupon();
            $coupon_discount = $coupon_model->update($coupon, ($result['subtotal'] - $coupon_price) * $rate);
            $result['order']['order_seller_id'] = $coupon_model->get_user_id($coupon);
        }

        $result['order']['order_total_default'] = round($result['order']['order_total'] * $rate, 2);
        $result['order']['order_subtotal_default'] = round($result['order']['order_subtotal'] * $rate, 2);
        $result['order']['order_shipping_default'] = round($result['order']['order_shipping'] * $rate, 2);
        if (isset($result['order']['order_transaction_fee'])) {
            $result['order']['order_transaction_fee_default'] = round($result['order']['order_transaction_fee'] * $rate, 2);
        }

        $result['order_model']->update($result['order'], $result['id']);

        $stats = new Stats();
        $stats->save([
            "stats_total" => $result['order']['order_total_default'],
            "stats_subtotal" => $result['order']['order_subtotal_default'],
            "stats_transaction_fee" => isset($result['order']['order_transaction_fee_default']) ? $result['order']['order_transaction_fee_default'] : 0,
            "stats_subtotal_without_coupon" => $result['order']['order_subtotal_default'] - $coupon_discount,
            "stats_shipping" => $result['order']['order_shipping_default'],
            "stats_coupon" => $coupon_discount,
        ]);
    }

    /** ==============================================================================================================================
     * Verifies the PAYPAL order
     * @noajax
     */
    public function verify() {
        $success = filter_input(INPUT_GET, 'success');
        $payment_id = filter_input(INPUT_GET, 'paymentId');
        $payer_id = filter_input(INPUT_GET, 'PayerID');
        $order = new Order();

        if ($success === 'true' && isset($payment_id) && !empty($payment_id) && isset($payer_id) && !empty($payer_id)) {
            $order->save(['order_id' => $payment_id, 'order_payer_id' => $payer_id]);
        }

        if (App::is_localhost()) {
            header('Location: ' . Payment::$LOCALHOST . '#order?p=' . substr($payment_id, 4, strlen($payment_id)));
        } else {
            header('Location: ' . Payment::$URL . '#order?p=' . substr($payment_id, 4, strlen($payment_id)));
        }
    }

    /** ==============================================================================================================================
     * Returns all client orders
     * @roles client, @nospamming
     */
    public function orders() {
        $user_id = (new User())->get_current_user_id();
        $id = filter_input(INPUT_GET, 'id');

        $order_model = new Order();
        if (!isset($id)) {
            App::response(200, $order_model->get_by_username($user_id));
        } else {
            App::response(200, $order_model->get_order_items_by($user_id, $id));
        }
    }

    /** ==============================================================================================================================
     * Gets all visible products or product by id
     * @nospamming false
     */
    public function get() {
        Currency::get();

        $data = json_decode(filter_input(INPUT_GET, "data"));
        if (isset($data->model) && strtolower($data->model) === "products") {
            if ($data->id === "") {
                App::response(200, (new Products)->all());
            } else {
                App::response(200, (new Products)->get($data->id));
            }
        }
    }

    /** ==============================================================================================================================
     * Gets cart and coupon information
     */
    public function cart() {
        Currency::get();

        $country = (new User)->get_current_user_country();
        App::response(200, array(
            "cart" => (new Products)->cart(),
            "coupon" => (new Coupon)->verify(),
            "country" => $country ? Store::get_shipping_zone_from_country($country) : null,
        ));
    }

    /** ==============================================================================================================================
     * Initialize currency options
     */
    public function options() {
        Currency::get();
    }

    /** ==============================================================================================================================
     * Returns the coupon 
     */
    public function coupon() {
        $search = filter_input(INPUT_GET, "data");
        $coupon = (new Coupon)->get($search);
        $return = [
            "coupon" => $coupon,
            "order" => $this->price(true, $coupon)
        ];

        App::response($coupon ? 200 : 400, $return);
    }

    /** ==============================================================================================================================
     * Gets user account information
     * @roles client
     */
    public function account() {
        $user = new User();
        App::response(200, $user->get_current_user_info());
    }

    /** ==============================================================================================================================
     * If user is connected the contact_us form is different
     * @roles user
     */
    public function mail_connected() {
        App::response(200, 'YES');
    }

    /** ==============================================================================================================================
     * Verifies contact us form
     */
    public function mail() {
        $data = json_decode(file_get_contents('php://input'));
        if (!isset($data)) {
            return;
        }

        $user = new User();
        $id = $user->get_current_user_fields(['user_id', 'user_name', 'user_first_name']);

        if (!$id) {
            UTILS::validate(Validate::EMAIL, $data->email);
            UTILS::validate(Validate::LENGTH, $data->name);
        }
        UTILS::validate(Validate::LENGTH, $data->mess);

        $fields = array("contact_us_mess" => $data->mess);
        if (!$id) {
            $fields['contact_us_email'] = $data->email;
            $fields['contact_us_name'] = $data->name;
        } else {
            $fields['contact_us_user_id'] = $id['user_id'];
        }

        (new ContactUs)->save($fields);
        $this->mail_send($id, $fields);
    }

    /** ==============================================================================================================================
     * Sends a contact us mail to admin
     */
    private function mail_send($id, $fields) {
        $name = !$id ? $fields['contact_us_name'] : $id['user_first_name'];
        $email = !$id ? $fields['contact_us_email'] : $id['user_name'];

        require_once './Mail/Mail.php';
        \Mail\Mail::send(array(
            "fromName" => $name, "fromEmail" => $email,
            "template" => "contact_us", "email" => 'asdasdasd', "subject" => "New Contact Us",
            "data" => array(
                "name" => 'admin',
                "customer" => $name,
                "mess" => $fields["contact_us_mess"]
            )
        ));

        App::response(200, 'YES');
    }

    /** ==============================================================================================================================
     * Gets seller sales
     * @roles seller
     */
    public function my_sales() {
        Currency::get();

        $model_user = new User();
        $model_coupon = new Coupon();
        $model_claim = new Claim();

        $user_id = $model_user->get_current_user_id();
        App::response(200, ["coupon" => $model_coupon->get_by_user_id($user_id), "claims" => $model_claim->get_by_user_id($user_id)]);
    }

    /** ==============================================================================================================================
     * Gets seller sales
     * @roles seller
     */
    public function receive_payment() {
        Currency::get();

        $v = $this->receive_payment_verify();
        $v["coupon_model"]->disable($v["coupon"]['coupon_id']);

        $id = uniqid();
        $rate = (new Currency)->get_new_rate($v["currency"], true);
        $price = round($v["price"] * $rate, 2);

        $result = (new Payment())->pay_seller(["email" => $v["email"], "amount" => $price, "currency" => $v["currency"], "id" => $id]);
        $model_claim = new Claim();
        if ($result === 1) {
            $model_claim->save(["claim_id" => $id, "claim_user_id" => $v["user_id"], "claim_price" => $price, "claim_currency" => $v["currency"], "claim_email" => $v["email"]]);
            $v["coupon_model"]->update_price($v["coupon"]['coupon_id'], round($v["price"] / 0.95, 2));

            $stats = new Stats();
            $stats->save(["stats_coupon_payed" => $price]);
        }

        $v["coupon_model"]->enable($v["coupon"]['coupon_id']);
        App::response($result === 1 ? 200 : 400, $result === 1 ? ["claims" => $model_claim->get($id), "coupon" => $v["coupon_model"]->get_by_user_id($v["user_id"])] : "ERROR");
    }

    private function receive_payment_verify() {
        $data = json_decode(filter_input(INPUT_GET, "data"));
        UTILS::validate(Validate::EMAIL, $data->email);
        UTILS::validate(Validate::PASSWORD, $data->pass);
        $user = new User();

        if (!$user->verify_password($data->pass)) {
            App::response(401, 'BAD PASSWORD');
        }

        $user_id = $user->get_current_user_id();
        $currency = filter_input(INPUT_COOKIE, 'currency');
        $coupon_model = new Coupon();
        $coupon = $coupon_model->get_by_user_id($user_id, true);
        $price = $coupon['coupon_profit_total'] - $coupon['coupon_payed'];

        if (!($price > Coupon::$MIN_CLAIM)) {
            App::response(400, 'SELLER MUST HAVE AT LEAST 5$ OF PROFIT');
        }

        if (intval($coupon['coupon_disabled']) === 1) {
            App::response(401, 'DISABLED');
        }

        return ["coupon_model" => $coupon_model, "coupon" => $coupon, "currency" => $currency, "user_id" => $user_id, "email" => $data->email, "price" => $price];
    }

}
