<?php

namespace Controller;

use App;

use Model\User as ModelUser;
use Model\Currency;
use Model\AModel;
use Mail\Mail;
use utils\Validate;
use utils\UTILS;

class User {

    /** ==============================================================================================================================
     * Verifies user password and username
     */
    public function verify() {
        $user = new ModelUser();
        $user->verify(json_decode(filter_input(INPUT_GET, 'data')));
    }

    /** ==============================================================================================================================
     * Send user new password
     */
    public function forgot() {
        $email = filter_input(INPUT_GET, 'email');
        if (!isset($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit;
        }

        $user = new ModelUser();

        $result = $user->change_password($email);
        if ($result["result"] === 1) {
            require_once './Mail/Mail.php';
            Mail::send(array(
                "fromName" => "Arc-Sans-Ciel", "fromEmail" => "admin@arcsansciel.com",
                "template" => "forgot_password", "email" => $email, "subject" => "Lost password",
                "data" => array(
                    "password" => $result["password"],
                    "name" => $result["name"]
                )
            ));
        }

        print_r($result);
    }

    /** ==============================================================================================================================
     * Creates new user
     */
    public function create() {
        $data = json_decode(file_get_contents('php://input'));
        $this->verify_fields($data, true);

        $user = new ModelUser();
        $data->user_password = password_hash($data->user_password, PASSWORD_DEFAULT);
        $user->roles($data, ['client', 'user']);
        $user->save($data);
    }

    /** ==============================================================================================================================
     * Updates a user
     * @roles user
     */
    public function modify() {
        $user = new ModelUser();
        $data = json_decode(file_get_contents('php://input'));
        $this->verify_fields($data);

        $result = $user->update($data);
        App::response($result === 1 ? 200 : 400, $result === 1 ? "OK" : "ERROR");
    }

    /** ==============================================================================================================================
     * Updates a user
     * @roles user
     */
    public function modify_password() {
        $user = new ModelUser();
        $data = json_decode(file_get_contents('php://input'));
        if (!$user->verify_password($data->user_old_password)) {
            App::response(400, 'BAD OLD');
        }
        if (!isset($data->user_password) || !isset($data->user_repassword) || $data->user_password !== $data->user_repassword || empty($data->user_password) || empty($data->user_repassword)) {
            App::response(400, 'BAD FIELDS');
        }
        if ($data->user_password === $data->user_old_password) {
            App::response(400, 'NEW AND OLD MUST BE DIFFERENT');
        }

        $result = $user->update(["user_password" => password_hash($data->user_password, PASSWORD_DEFAULT)]);
        App::response($result === 1 ? 200 : 400, $result === 1 ? "OK" : "ERROR");
    }

    /** ==============================================================================================================================
     * Verifies user token
     */
    public function token() {
        Currency::get();
        $user = new ModelUser();
        $user->token2();
    }

    private function verify_fields(&$data, $exit = false) {
        $verify = AModel::get_accepted_properties(["user_name", "user_password", "user_retype_password", "user_first_name", "user_last_name", "user_phone_number", "user_state", "user_country", "user_city", "user_postal_code", "user_line1", "user_line2", "user_formatted"]);

        $array1 = $verify;
        $array2 = $data;
        if ($exit === false) {
            $array1 = $data;
            $array2 = $verify;
        }

        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $data) && $exit === true) {
                App::response(400, $key . ' CANNOT BE EQUAL TO ' . $value);
            } else if (!array_key_exists($key, $array2) && $exit === false) {
                unset($data->$key);
                continue;
            }
            $this->validate_field($key, $data->$key, $data->user_password);
        }

        if (isset($data->user_first_name)) {
            $data->user_first_name = strtolower($data->user_first_name);
        }
        if (isset($data->user_last_name)) {
            $data->user_last_name = strtolower($data->user_last_name);
        }
    }

    private function validate_field($key, $value, $pass) {
        switch ($key) {
            case "user_password":
                UTILS::validate(Validate::PASSWORD, $value);
                break;
            case "user_retype_password":
                UTILS::validate(Validate::SAME, $pass, $value);
                break;
            case "user_phone_number":
                UTILS::validate(Validate::PHONE, $value);
                break;
            case "user_name":
                UTILS::validate(Validate::EMAIL, $value);
                break;
            case "user_line2":
            case "user_id":
                break;
            default:
                UTILS::validate(Validate::LENGTH, $value);
                break;
        }
    }

}
