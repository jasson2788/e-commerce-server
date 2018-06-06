<?php

namespace Model;

use App;

class User extends AModel implements IModel {

    private $roles = ["user" => 1, "client" => 1, "seller" => 1];
    private $MAX_ATTEMPS = 10;

    /** ==============================================================================================================================
     * Verifies user authentication. Verifies if user is logged in, if ip is same, if user have needed roles
     */
    public function token($roles) {
        $params = ["uuid" => filter_input(INPUT_COOKIE, "uuid"), "name" => filter_input(INPUT_COOKIE, "name"), "token" => filter_input(INPUT_COOKIE, "token")];
        $fields = $this->verify_roles($roles, array());

        if (isset($params["uuid"]) && isset($params["name"]) && isset($params["token"])) {
            $user = App::$DATABASE->get("user_device", ["[>]user" => ["user_device_id" => "user_id"]], $fields, [
                "AND" => [
                    "user_device_uuid" => $params["uuid"], "user_name" => $params["name"], "user_device_token" => $params["token"], "user_device_ip" => App::get_client_ip()
                ]
            ]);
            if (!$user) {
                return false;
            }
            foreach ($user as $key) {
                if ($key !== "1") {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

    /** ==============================================================================================================================
     * Get current user specified fields
     */
    public function get_current_user_fields($fields) {
        $params = ["uuid" => filter_input(INPUT_COOKIE, "uuid"), "name" => filter_input(INPUT_COOKIE, "name"), "token" => filter_input(INPUT_COOKIE, "token")];
        return App::$DATABASE->get("user_device", ["[>]user" => ["user_device_id" => "user_id"]], $fields, [
                    "AND" => [
                        "user_device_uuid" => $params["uuid"], "user_name" => $params["name"], "user_device_token" => $params["token"], "user_device_ip" => App::get_client_ip()
                    ]
        ]);
    }

    /** ==============================================================================================================================
     * Verifies the current user password
     */
    public function verify_password($pass) {
        $hash = App::$DATABASE->get("user", "user_password", ["user_name" => filter_input(INPUT_COOKIE, "name")]);
        return password_verify($pass, $hash);
    }

    /** ==============================================================================================================================
     * Verifies user authentication. Verifies if user is logged in, if ip is same and returns roles
     */
    public function token2($return = null) {
        $params = ["uuid" => filter_input(INPUT_COOKIE, "uuid"), "name" => filter_input(INPUT_COOKIE, "name"), "token" => filter_input(INPUT_COOKIE, "token")];
        $fields = $this->get_roles_fields();
        $user = App::$DATABASE->get("user_device", ["[>]user" => ["user_device_id" => "user_id"]], $fields, [
            "AND" => [
                "user_device_uuid" => $params["uuid"], "user_name" => $params["name"], "user_device_token" => $params["token"], "user_device_ip" => App::get_client_ip()
            ]
        ]);


        $result = $user ? $user : 'NOT FOUND';

        if (!isset($return)) {
            App::response($user ? 200 : 400, $result);
        } else {
            return $result;
        }
    }

    public function is_logged_in() {
        return $this->token2(true) !== 'NOT FOUND' ? true : false;
    }

    /** ==============================================================================================================================
     * Gets all roles fields
     */
    private function get_roles_fields() {
        $fields = array();
        foreach ($this->roles as $key => $value) {
            array_push($fields, "user_role_" . $key);
        }
        return $fields;
    }

    /** ==============================================================================================================================
     * Only get roles
     */
    private function only_get_roles($hash) {
        foreach ($hash as $key => $value) {
            if (!App::starts_with($key, 'user_role_')) {
                unset($hash[$key]);
            }
        }
        return $hash;
    }

    /** ==============================================================================================================================
     * Verifies if all roles are valid
     */
    private function verify_roles($roles, $fields) {
        if ($roles[0] === "1") {
            echo "NO ROLES SPECIFIED";
            exit;
        }

        foreach ($roles as $role) {
            if (!array_key_exists($role, $this->roles)) {
                echo "ROLE " . $role . " DOES NOT EXIST";
                exit;
            } else {
                array_push($fields, "user_role_" . $role);
            }
        }
        return $fields;
    }

    /** ==============================================================================================================================
     * Verifies LOGIN information
     */
    public function verify($data) {
        if (!isset($data) || !isset($data->name) || !isset($data->uuid) || !isset($data->pass)) {
            App::response(400, 'NOT_FOUND');
        }

        $hash = App::$DATABASE->get("user", array_merge(["user_id", "user_name", "user_password", "user_wrong_pass"], $this->get_roles_fields()), ["user_name" => $data->name]);
        if ($this->verify_pass_and_attemps($hash, $data->pass)) {
            $token = App::uuid();
            if (App::$DATABASE->get("user_device", "user_device_uuid", ["AND" => ["user_device_uuid" => $data->uuid]])) {
                App::$DATABASE->update("user_device", ["user_device_token" => $token, "user_device_ip" => App::get_client_ip(), "user_device_id" => $hash['user_id']], ["user_device_uuid" => $data->uuid]);
            } else {
                App::$DATABASE->insert('user_device', ['user_device_uuid' => $data->uuid, 'user_device_id' => $hash['user_id'], 'user_device_token' => $token, "user_device_ip" => App::get_client_ip()]);
            }

            App::set_cookie('name', $data->name, time() + 3600 * 24 * 365);
            App::set_cookie('token', $token, time() + 3600 * 24 * 365);
            App::response(200, $this->only_get_roles($hash));
        }
    }

    /** ==============================================================================================================================
     * Returns shipping address
     */
    public function shipping_address($shipping_address = null) {
        if (isset($shipping_address)) {
            return array(
                'user_state' => $shipping_address->state,
                'user_country' => $shipping_address->country,
                'user_city' => $shipping_address->city,
                'user_postal_code' => $shipping_address->postal_code,
                'user_line1' => $shipping_address->line1,
                'user_line2' => $shipping_address->line2,
                'user_formatted' => $shipping_address->formatted,
            );
        } else {
            $name = filter_input(INPUT_COOKIE, 'name');
            if (!isset($name) || empty($name)) {
                return [];
            }
            return App::$DATABASE->get("user", ['user_state', 'user_country', 'user_city', 'user_postal_code', 'user_line1', 'user_line2', 'user_formatted'], ['user_name' => $name]);
        }
    }

    /** ==============================================================================================================================
     * Verifies number of attempts before lockdown
     */
    private function verify_pass_and_attemps($hash, $pass) {
        if ($hash && $hash["user_wrong_pass"] >= $this->MAX_ATTEMPS) {
            App::response(401, 'UNAUTHORIZED');
        } else if ($hash && password_verify($pass, $hash["user_password"])) {
            if ($hash["user_wrong_pass"] > 0) {
                App::$DATABASE->update("user", ["user_wrong_pass" => 0], ["user_name" => $hash["user_name"]]);
            }
            return true;
        } else {
            if ($hash) {
                $wrong_pass = $hash["user_wrong_pass"] + 1;
                App::$DATABASE->update("user", ["user_wrong_pass" => $wrong_pass], ["user_name" => $hash["user_name"]]);
            }
            App::response(400, 'BAD EMAIL OR PASSWORD');
        }
    }

    /** ==============================================================================================================================
     * Saves a user
     */
    public function save($element) {
        $exists = $this->exists($element->user_name);
        if (!isset($element->user_id) && $exists) {
            App::response(400, 'USER ALREADY EXISTS');
        }

        if (!isset($element->user_id)) {
            $pass = $element->user_retype_password;
            unset($element->user_retype_password);
            App::$DATABASE->insert("user", (array) $element);
            $this->verify((object) array('name' => $element->user_name, 'pass' => $pass, 'uuid' => filter_input(INPUT_COOKIE, 'uuid')));
        }
    }

    /** ==============================================================================================================================
     * Updates a user
     */
    public function update($element) {
        return App::$DATABASE->update("user", (array) $element, ["user_id" => $this->get_current_user_id()]);
    }

    /** ==============================================================================================================================
     * Determines if user exists
     */
    public function exists($user_name) {
        return App::$DATABASE->get("user", "user_first_name", ["user_name" => $user_name]);
    }

    /** ==============================================================================================================================
     * Change roles of element
     */
    public function roles(&$element, $roles) {
        $fields = $this->get_roles_fields();
        foreach ($fields as $field) {
            $element->$field = 0;
            foreach ($roles as $role) {
                if ($field === 'user_role_' . $role) {
                    $element->$field = 1;
                }
            }
        }
    }

    /** ==============================================================================================================================
     * Change password of user to a random one
     */
    public function change_password($id) {
        $name = App::$DATABASE->get("user", "user_first_name", ["user_name" => $id]);
        if ($name) {
            $p = $this->random_str(20);
            return array("result" => App::$DATABASE->update("user", ["user_password" => password_hash($p, PASSWORD_DEFAULT), "user_wrong_pass" => 0], ["user_name" => $id]), "password" => $p, "name" => $name);
        } else {
            return array("result" => 0);
        }
    }

    /** ==============================================================================================================================
     * generates random string
     */
    private function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"/$%?&*():;') {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        if ($max < 1) {
            throw new Exception('$keyspace must be at least two characters long');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[rand(0, $max)];
        }
        return $str;
    }

    /** ==============================================================================================================================
     * gets current user info
     */
    public function get_current_user_info() {
        return App::$DATABASE->get("user", ["user_name", "user_first_name", "user_last_name", "user_phone_number", "user_state", "user_country",
                    "user_city", "user_postal_code", "user_line1", "user_line2", "user_formatted"], ["user_name" => $this->get_current_user_name()]);
    }

    /** ==============================================================================================================================
     * gets current user country
     */
    public function get_current_user_country() {
        return App::$DATABASE->get("user", "user_country", ["user_name" => filter_input(INPUT_COOKIE, 'name')]);
    }

    private function get_current_user_name() {
        $name = filter_input(INPUT_COOKIE, 'name');
        if (!isset($name) || empty($name)) {
            App::response(400, 'NO NAME');
        }
        return $name;
    }

    public function get_current_user_id($exit = true) {
        $user_name = filter_input(INPUT_COOKIE, 'name');
        if ($exit) {
            $user_name = $this->get_current_user_name();
        }
        return (int) App::$DATABASE->get("user", "user_id", ['user_name' => $user_name]);
    }

    public function all() {
        
    }

    public function get($id) {
        
    }

    public function remove($id) {
        
    }

}
