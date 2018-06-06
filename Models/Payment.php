<?php

namespace Model;

use App;
use Exception;

class Payment {

    private static $API_CONTEXT;
    private static $API_CLIENT_TEST = 'ATiZ7u0TwsfXsSLknkHYEEkoCv0ksv6ELC76-Z-1-miqfKna-yET56BX-VAfRxJOG7HiBkgp9ag0YIzv';
    private static $API_SECRET_TEST = 'ELEPHM6aNYeqFYrAuSNy6jn1vEr2c2JTUpuXGqzc0NFicxNPzcFN_r5F6xIFlTkqVULKh83qAi8WneiZ';
    private static $API_CLIENT = 'AUVasl0rJfxkfSW-Ml7R3onfFD90DZWpRuo7f6TOW2EAS8hYWxfPgP1g5vy-r8GAIAj6xA6pDnsGiLtX';
    private static $API_SECRET = 'EPMvowzU0RWLlfE4YOvoX_-FppXn2Xc7qElVWaCp74y9yYCrOevx_JGaG3znhCPm95XcCaW9SoUEHWDM';
    public static $URL = '';
    public static $LOCALHOST = '';
    private static $RETURN = '';
    private static $DESCRIPTION = '';
    private static $NAME = '';

    public function __construct() {
        require_once './PayPal-PHP-SDK/autoload.php';
        if (App::is_localhost()) {
            Payment::$API_CONTEXT = new \PayPal\Rest\ApiContext(
                    new \PayPal\Auth\OAuthTokenCredential(Payment::$API_CLIENT_TEST, Payment::$API_SECRET_TEST)
            );
        } else {
            Payment::$API_CONTEXT = new \PayPal\Rest\ApiContext(
                    new \PayPal\Auth\OAuthTokenCredential(Payment::$API_CLIENT, Payment::$API_SECRET)
            );
            Payment::$API_CONTEXT->setConfig(['mode' => 'live']);
        }
    }

    public function order($order, $id) {
        if (Products::SHIPPING === false) {
            //$experience_id = $this->get_experience_id();
        } 
        
        return $this->order_link($order, $id, $experience_id);
    }

    public function get_experience_id() {
        $flowConfig = new \PayPal\Api\FlowConfig();
        $flowConfig->setLandingPageType("Billing");
        $flowConfig->setBankTxnPendingUrl(Payment::$URL);
        $flowConfig->setUserAction("commit");
        $flowConfig->setReturnUriHttpMethod("GET");

        $presentation = new \PayPal\Api\Presentation();
        $presentation->setLogoImage("http://www.yeowza.com/favico.ico")->setBrandName(Payment::$NAME)->setLocaleCode("CA")->setReturnUrlLabel("Return")->setNoteToSellerLabel("Thanks!");
        $inputFields = new \PayPal\Api\InputFields();
        $inputFields->setNoShipping(1)->setAddressOverride(0);
        $webProfile = new \PayPal\Api\WebProfile();
        $webProfile->setName(Payment::$NAME . uniqid())->setFlowConfig($flowConfig)->setPresentation($presentation)->setInputFields($inputFields)->setTemporary(false);
        try {
            $createProfileResponse = $webProfile->create(Payment::$API_CONTEXT);
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            App::response(400, "ERROR");
        }
        return $createProfileResponse->getId();
    }

    public function order_link($order, $id, $experience_profile_id = null) {
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod("paypal");

        $details = new \PayPal\Api\Details();
        $amount = new \PayPal\Api\Amount();
        $transaction = new \PayPal\Api\Transaction();
        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $payment = new \PayPal\Api\Payment();

        try {
            $country = $this->country($order->shipping_zone);
            $shipping = Products::SHIPPING === false ? 0 : $this->get_shipping($order, $country);
            $totalwithshipping = Products::SHIPPING === false ? $order->total : $this->get_total_with_shipping($order, $country);
            
            $details->setSubtotal($order->total)->setShipping($shipping);
            $amount->setCurrency($order->currency['currency'])->setTotal($totalwithshipping)->setDetails($details);
            $transaction->setAmount($amount)->setItemList($this->items($order))->setDescription(Payment::$DESCRIPTION)->setInvoiceNumber(uniqid() . "|" . $country . "|" . $id);
            $redirectUrls->setReturnUrl(Payment::$RETURN . "?success=true")->setCancelUrl(Payment::$RETURN . "verify?success=false");
            $payment->setIntent("order")->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array($transaction));
            
            if(Products::SHIPPING === false) {
                $payment->setExperienceProfileId('XP-PYBQ-EWLW-5SUE-TH3Z');
            }

            $payment->create(Payment::$API_CONTEXT);
            return $payment->getApprovalLink();
        } catch (Exception $ex) {
            App::response(400, "ERROR");
        }
    }

    private function get_total_with_shipping($order, $country_code) {
        if (strtolower($country_code) === 'us') {
            return $order->totalwithshipping['USA'];
        } else if (strtolower($country_code) === 'ca') {
            return $order->totalwithshipping['Canada'];
        } else {
            return $order->totalwithshipping['International'];
        }
    }

    private function get_shipping($order, $country_code) {
        if (strtolower($country_code) === 'us') {
            return $order->shipping_price['USA'];
        } else if (strtolower($country_code) === 'ca') {
            return $order->shipping_price['Canada'];
        } else {
            return $order->shipping_price['International'];
        }
    }

    private function country($shipping_zone) {
        if (strtolower($shipping_zone) === 'usa') {
            return 'us';
        } else if (strtolower($shipping_zone) === 'canada') {
            return 'ca';
        } else {
            return '';
        }
    }

    private function items($order) {
        $itemList = new \PayPal\Api\ItemList();
        $itemArray = array();
        foreach ($order->items as $element) {
            $item = new \PayPal\Api\Item();
            $item->setName($element['title'])->setCurrency($order->currency['currency'])->setQuantity($element['quantity'])->setPrice($element['price'])->setSku($element['id'] . '|' . $element['size']);
            array_push($itemArray, $item);
        }

        if ($order->discount !== 0) {
            $discount = new \PayPal\Api\Item();
            $discount->setName('code: ' . $order->discount_code)->setCurrency($order->currency['currency'])->setQuantity(1)->setPrice(-$order->discount);
            array_push($itemArray, $discount);
        }

        $itemList->setItems($itemArray);
        return $itemList;
    }

    public function verify($id, $payer_id, $order_model) {
        $payment = \PayPal\Api\Payment::get($id, Payment::$API_CONTEXT);
        $order_model->update_processing($id, 2);
        $execution = new \PayPal\Api\PaymentExecution();
        $execution->setPayerId($payer_id);

        try {
            $payment->execute($execution, Payment::$API_CONTEXT);
            $order_model->update_processing($id, 3);
            $order = $payment->transactions[0]->related_resources[0]->order;
            $amount = $payment->transactions[0]->getAmount();
            return $this->capture($order, $amount->getCurrency(), $amount->getTotal(), $payment, $order_model, $id, $this->get_user_id($payment->transactions[0]->getInvoiceNumber()));
        } catch (Exception $ex) {
            $order_model->update_processing($id, 98);
            App::response(400, "FAILED TO EXECUTE");
        }
    }

    private function get_user_id($invoice) {
        $result = explode('|', $invoice);
        return $result[2] !== 0 ? $result[2] : null;
    }

    private function capture($order, $currency, $total, $payment, $order_model, $id, $user_id) {
        $amount = new \PayPal\Api\Amount();
        $amount->setCurrency($currency)->setTotal($total);

        $captureDetails = new \PayPal\Api\Authorization();
        $captureDetails->setAmount($amount);

        try {
            $result = $order->capture($captureDetails, Payment::$API_CONTEXT);
            $order_model->update_processing($id, 4);
            return $this->order_info($payment->transactions[0], $id, $order_model, $result, $user_id, $payment);
        } catch (Exception $ex) {
            $order_model->update_processing($id, 99);
            App::response(400, "FAILED TO CAPTURE");
        }
    }

    private function order_info($transaction, $id, $order_model, $result, $user_id, $payment) {
        $payer_info = $payment->getPayer()->getPayerInfo();       
        $shipping_address = $transaction->getItemList()->getShippingAddress();
        $amount = $transaction->getAmount();
        $transaction_fee = $result->getTransactionFee();
        
        $order = array(
            "order_state" => Products::SHIPPING === false ? null : $shipping_address->getState(),
            "order_country" => Products::SHIPPING === false ? null : $shipping_address->getCountryCode(),
            "order_city" => Products::SHIPPING === false ? null : $shipping_address->getCity(),
            "order_postal_code" => Products::SHIPPING === false ? null : $shipping_address->getPostalCode(),
            "order_line1" => Products::SHIPPING === false ? null : $shipping_address->getLine1(),
            "order_line2" => Products::SHIPPING === false ? null : $shipping_address->getLine2(),
            "order_total" => $amount->getTotal(),
            "order_shipping" => $amount->getDetails()->getShipping(),
            "order_subtotal" => $amount->getDetails()->getSubtotal(),
            "order_currency" => $amount->getCurrency(),
            "order_email" => $payer_info->getEmail(),
            "order_first_name" => $payer_info->getFirstName(),
            "order_last_name" => $payer_info->getLastName()
        );

        if (isset($user_id)) {
            $order["order_user_id"] = $user_id;
        }

        if (isset($transaction_fee)) {
            $order["order_transaction_fee"] = $transaction_fee->getValue();
        }

        return array(
            "items" => $transaction->getItemList()->getItems(),
            "id" => $id,
            "user_id" => $user_id,
            "subtotal" => $amount->getDetails()->getSubtotal(),
            "currency" => $amount->getCurrency(),
            "order" => $order,
            "order_model" => $order_model
        );
    }

    public function pay_seller($params) {
        $payouts = new \PayPal\Api\Payout();
        $currency = new \PayPal\Api\Currency();
        $currency->setCurrency($params["currency"])->setValue($params["amount"]);
        $sender_batch_header = new \PayPal\Api\PayoutSenderBatchHeader();
        $sender_batch_header->setSenderBatchId(uniqid())->setEmailSubject("You have a payment");
        $sender_item = new \PayPal\Api\PayoutItem();
        $sender_item->setRecipientType('Email')
                ->setNote('Thanks you.')
                ->setReceiver($params['email'])
                ->setAmount($currency);
        $payouts->setSenderBatchHeader($sender_batch_header)->addItem($sender_item);
        try {
            $payouts->create(null, self::$API_CONTEXT);
            return 1;
        } catch (Exception $ex) {
            return 0;
        }
    }

}
