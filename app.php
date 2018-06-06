<?php

session_start();

class App {

    public static $DATABASE;

    /** ==============================================================================================================================
     * Initialize the database
     */
    public static function initDatabase() {
        if (!isset(App::$DATABASE) && isset(App::$PARAMETERS) && isset(App::$PARAMETERS['database'])) {
            try {
                $p = App::is_localhost() ? App::$PARAMETERS['database']['local'] : App::$PARAMETERS['database']['prop'];
                App::$DATABASE = new medoo([
                    'database_type' => 'mysql',
                    'database_name' => $p['name'],
                    'server' => 'localhost',
                    'username' => $p['user'],
                    'password' => $p['pass'],
                    'charset' => 'utf8'
                ]);
            } catch (Exception $e) {
                echo "ERROR";
                die();
            }
        }
    }

    private static $PARAMETERS;

    /** ==============================================================================================================================
     * Initialize the parameters of the framework, calls the controller and the function
     * @param type $params array 
     */
    public function __construct($params) {
        if (!isset(App::$PARAMETERS)) {
            App::$PARAMETERS = $params;
        }

        if (isset(App::$PARAMETERS['cors']) && is_array(App::$PARAMETERS['cors']) && isset(App::$PARAMETERS['cors']['allow']) && is_string(App::$PARAMETERS['cors']['allow'])) {
            $this->allow_cors();
        }

        if (isset(App::$PARAMETERS['error_reporting'])) {
            error_reporting(App::$PARAMETERS['error_reporting']);
        }

        $this->request();
    }

    /** ==============================================================================================================================
     * Replaces system getenv...
     */
    public static function getenv($name) {
        return $_SERVER[$name];
    }

    /** ==============================================================================================================================
     * Alow CORS at the specified ip
     */
    private function allow_cors() {
        if (self::getenv('HTTP_ORIGIN') !== null) {
            header("Access-Control-Allow-Origin: " . App::$PARAMETERS['cors']['allow']);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }

        if (self::getenv('REQUEST_METHOD') == 'OPTIONS') {
            if (self::getenv['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] !== null) {
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            }
            if (self::getenv('HTTP_ACCESS_CONTROL_REQUEST_HEADERS') !== null) {
                header("Access-Control-Allow-Headers: " . self::getenv('HTTP_ACCESS_CONTROL_REQUEST_HEADERS'));
            }
            exit(0);
        }
    }

    /** ==============================================================================================================================
     * Returns if environment is localhost
     */
    public static function is_localhost() {
        return in_array(self::getenv('REMOTE_ADDR'), self::get_localhost_addresses());
    }

    /** ==============================================================================================================================
     * Returns all localhost addresses
     */
    public static function get_localhost_addresses() {
        $local_ips = array('127.0.0.1', '::1');

        if (isset(App::$PARAMETERS['mobile_testing_ips'])) {
            $local_ips = array_merge($local_ips, App::$PARAMETERS['mobile_testing_ips']);
        }

        return $local_ips;
    }

    /** ==============================================================================================================================
     * Parses and verifies the given request, and invoke the good controller and the good method
     */
    private function request() {
        $filter = strtolower(filter_input(INPUT_GET, 'query'));
        $query = isset($filter) ? $filter : "";

        if ($query == "") {
            exit;
        }

        $params = ["controller" => ucfirst(strtolower(explode("/", $query)[0])), "function" => strtolower(explode("/", $query)[1])];

        $controller_path = "Controllers/" . $params['controller'] . ".php";
        if (file_exists($controller_path)) {
            require_once $controller_path;
            $controller = new ReflectionClass('\Controller\\' . $params['controller']);
            $class = $controller->newInstance();
            if (isset($params['function']) && $this->is_function_valid($params['function'], $class)) {
                $this->invoke($class, $params, $controller);
            } else {
                http_response_code(400);
            }
        } else {
            http_response_code(404);
        }
    }

    /** ==============================================================================================================================
     * Invoke the given request
     */
    private function invoke($class, $params, $controller) {
        ob_end_clean();

        $method = new ReflectionMethod('\Controller\\' . $params['controller'], $params['function']);
        if ($this->no_spamming($controller, $method) && $this->request_access($controller, $method) && $this->ajax($controller, $method)) {
            if (App::$PARAMETERS['gzip'] === 1 && $this->get_params_from_doc_comments($method->getDocComment(), "nogzip") === false) {
                ob_start("ob_gzhandler");
            }
            $method->invoke($class);
        }
    }

    /** ==============================================================================================================================
     * Verifies if function can be called not from an ajax request
     */
    private function ajax($controller, $method) {
        if ($this->get_params_from_doc_comments($controller->getDocComment(), "noajax") !== false || $this->get_params_from_doc_comments($method->getDocComment(), "noajax") !== false || !isset(App::$PARAMETERS['ajax_only']) || App::$PARAMETERS['ajax_only'] !== 1) {
            return true;
        }


        $requested = self::getenv('HTTP_X_REQUESTED_WITH', true);
        if (empty($requested) || strtolower($requested) !== 'xmlhttprequest') {
            echo "NOT_AN_AJAX_REQUEST";
            exit;
        }
        return true;
    }

    /** ==============================================================================================================================
     * Verifies if a function can be invoked from a given controller (exists, public)
     */
    private function is_function_valid($func, $class) {
        if (!method_exists($class, $func)) {
            return false;
        }

        $reflection = new ReflectionMethod($class, $func);
        if (!$reflection->isPublic()) {
            return false;
        }

        return true;
    }

    /** ==============================================================================================================================
     * Request access to function if controller is admin
     */
    private function request_access($controller, $method) {
        $roles_c = $this->get_params_from_doc_comments($controller->getDocComment(), "roles");
        $roles_m = $this->get_params_from_doc_comments($method->getDocComment(), "roles");

        if (!$roles_c && !$roles_m) {
            return true;
        }

        $roles = array();
        if ($roles_c !== false) {
            $roles = array_merge($roles, explode('|', $roles_c));
        } if ($roles_m !== false) {
            $roles = array_merge($roles, explode('|', $roles_m));
        }

        if (!(new \Model\User())->token(array_unique($roles))) {
            self::response(401, 'LOCKED');
        } else {
            return true;
        }
    }

    /** ==============================================================================================================================
     * Finds value of specified parameter in comments of function or controller
     */
    private function get_params_from_doc_comments($doc_comments, $params) {
        $p = strrpos($doc_comments, "@" . $params);

        if (!$p) {
            return false;
        }

        $z = preg_replace('/\s+/', '', substr($doc_comments, $p + strlen("@" . $params)));
        if ($z === "*/" || $z[0] === ',') {
            return true;
        }

        $v = substr($doc_comments, $p + strlen("@" . $params . " "));
        $c = str_split($v);
        foreach ($c as $char) {
            if (ctype_space($char)) {
                break;
            }
            $value .= $char;
        }

        if (strrpos($value, ',')) {
            $value = substr($value, 0, strrpos($value, ','));
        }
        if (strrpos($value, '*/')) {
            $value = substr($value, 0, strrpos($value, '*/'));
        }
        return $value;
    }

    /** ==============================================================================================================================
     * Prevents function spamming, user must wait at least x seconds
     */
    private function no_spamming($controller, $func) {
        $wait = $this->get_params_from_doc_comments($controller->getDocComment(), "nospamming");
        if (!$wait) {
            $wait = $this->get_params_from_doc_comments($func->getDocComment(), "nospamming");
        }

        if ($wait === true || $wait === 'false') {
            return true;
        }

        if (is_bool($wait) || !is_int((int) $wait)) {
            $wait = isset(App::$PARAMETERS['spamming']) && is_int(App::$PARAMETERS['spamming']) ? App::$PARAMETERS['spamming'] : 1;
        }

        if ($this->no_spamming_sesson($func, $wait)) {
            return true;
        } else {
            echo "NOSPAMMING";
            exit;
        }
    }

    /** ==============================================================================================================================
     * Verifies if user waited x seconds before requesting function
     */
    private function no_spamming_sesson($func, $wait) {
        if (!isset($_SESSION['func'])) {
            $_SESSION['func'] = [];
        }
        if (!isset($_SESSION['func'][$func->getName()])) {
            $_SESSION['func'][$func->getName()] = time();
            return true;
        }

        if (time() - $_SESSION['func'][$func->getName()] < $wait) {
            $_SESSION['func'][$func->getName()] = time();
            return false;
        } else {
            $_SESSION['func'][$func->getName()] = time();
            return true;
        }
    }

    /** ==============================================================================================================================
     * Returns the client ip address
     */
    public static function get_client_ip() {
        $ipaddress = '';
        if (self::getenv('HTTP_CLIENT_IP')) {
            $ipaddress = self::getenv('HTTP_CLIENT_IP');
        } else if (self::getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = self::getenv('HTTP_X_FORWARDED_FOR');
        } else if (self::getenv('HTTP_X_FORWARDED')) {
            $ipaddress = self::getenv('HTTP_X_FORWARDED');
        } else if (self::getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = self::getenv('HTTP_FORWARDED_FOR');
        } else if (self::getenv('HTTP_FORWARDED')) {
            $ipaddress = self::getenv('HTTP_FORWARDED');
        } else if (self::getenv('REMOTE_ADDR')) {
            $ipaddress = self::getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }

        if (in_array($ipaddress, self::get_localhost_addresses())) {
            return '192.206.151.131'; // RUSSIA 109.172.27.14, ZIMBABWE 197.221.251.24, CANADA 192.206.151.131, USA 35.185.72.186
        }
        
        return $ipaddress;
    }

    /** ==============================================================================================================================
     * Returns a uuid
     */
    public static function uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }

    /** ==============================================================================================================================
     * Sets a cookie
     */
    public static function set_cookie($name, $value, $time) {
        setrawcookie($name, rawurlencode($value), $time, "/", "", false, false);
    }

    /** ==============================================================================================================================
     * Search backwards starting from haystack length characters from the end
     */
    public static function starts_with($haystack, $needle) {
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }

    /** ==============================================================================================================================
     * Returns a response
     */
    public static function response($code, $response) {
        echo json_encode(array('code' => $code, 'response' => $response));
        exit;
    }

}

spl_autoload_register(function ($class_name) {
    $include = $class_name;
    if (App::starts_with(strtolower($class_name), 'model')) {
        $include = str_replace("Model", "Models", $class_name);
        if ($class_name === 'Model\AModel') {
            include_once './Models/Model.php';
            return;
        }
    }
    if (App::starts_with(strtolower($class_name), 'utils') || App::starts_with(strtolower($class_name), 'validate')) {
        include './utils.php';
    }
    $include = './' . str_replace("\\", "/", $include) . '.php';
    include $include;
});