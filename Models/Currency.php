<?php

namespace Model;

use App;
use Model\User;

class Currency {
    
    const DEFAULT_CURRENCY = 'CAD';
    
    public static function get() {
        return Currency::get_rate(Currency::get_currency());
    }

    /** ==============================================================================================================================
     * Verifies if currency rate needs an update
     * @param type $cu
     * @return type
     */
    private static function get_rate($cu) {
        if (isset($_SESSION['currency_rate']) && isset($_SESSION['currency_rate_date']) && $_SESSION['currency_rate_date'] < (time() + 3600 / 2) && $_SESSION['currency_rate_currency'] === $cu) {
            App::set_cookie('currency_rate', $_SESSION['currency_rate'], 0);
        } else {
            $_SESSION['currency_rate_currency'] = $cu;

            if (strtolower($cu) === strtolower(Currency::DEFAULT_CURRENCY)) {
                Currency::set_rate(1);
                return;
            }

            Currency::get_new_rate($cu);
        }

        return ["currency" => $cu, "rate" => $_SESSION['currency_rate']];
    }

    /** ==============================================================================================================================
     *  Gets the default rate from yahoo finance api
     * @param type $cu
     */
    public static function get_default_rate($cu) {
        $ch = curl_init('http://download.finance.yahoo.com/d/quotes.csv?s=' . $cu . Currency::DEFAULT_CURRENCY . '=X&f=l1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, getenv("HTTP_USER_AGENT"));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $rt = curl_exec($ch);
        curl_close($ch);
        return floatval($rt);
    }

    /** ==============================================================================================================================
     *  Gets new currency rate from yahoo finance api
     * @param type $cu
     */
    public static function get_new_rate($cu, $return = null) {
        $ch = curl_init('https://api.fixer.io/latest?base=' . Currency::DEFAULT_CURRENCY . '&symbols=' . $cu);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = json_decode(curl_exec($ch));
        $rt = $result->rates->$cu;
        
        if ($return !== true) {
            Currency::set_rate(floatval($rt));
        } else {
            return floatval($rt);
        }
        curl_close($ch);
    }

    /** ==============================================================================================================================
     * Returns the currency based on ip address of the client
     * @return type
     */
    private static function get_currency() {
        if (filter_input(INPUT_COOKIE, 'currency') !== null && filter_input(INPUT_COOKIE, 'shipping_zone') !== null) {
            return filter_input(INPUT_COOKIE, 'currency');
        }

        $ch = curl_init('http://www.geoplugin.net/php.gp?ip=' . App::get_client_ip());
        $rt = '';

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (($rt = curl_exec($ch)) === false) {
            echo "ERROR";
            curl_close($ch);
            exit;
        } else {
            $un = unserialize($rt);
            $rt = array_key_exists($un['geoplugin_currencyCode'], Currency::get_accepted_currencies()) ? $un['geoplugin_currencyCode'] : Currency::DEFAULT_CURRENCY;
            setcookie('currency', $rt, time() + 3600 * 24 * 365, "/", "", false, false);

            $zn = Store::get_shipping_zone_from_country($un['geoplugin_countryCode']);
            setcookie('shipping_zone', $zn, time() + 3600 * 24 * 365, "/", "", false, false);

            curl_close($ch);
        }
        return $rt;
    }

    /** ==============================================================================================================================
     * Sets the currency rate
     * @param type $rt
     */
    private static function set_rate($rt) {
        $_SESSION['currency_rate'] = $rt;
        $_SESSION['currency_rate_date'] = time();
        App::set_cookie('currency_rate', $rt, 0);
    }

    /** ==============================================================================================================================
     * Returns an array of accepted currency symbols
     * @return type
     */
    private static function get_accepted_currencies() {
        return [
            "USD" => true, "CAD" => true, "GBP" => true, "EUR" => true, "AUD" => true,
            "JPY" => true, "RUB" => true, "HKD" => true, "CHF" => true
        ];
    }

}
