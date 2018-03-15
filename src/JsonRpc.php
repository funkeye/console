<?php
/**
 * pinzweb\console
 * Copyright (C) 2018  pinzweb.at GmbH & Co KG
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace pinzweb\Console;
use pinzweb\Console\Exceptions\JsonRpcExeption;

class JsonRpc {
    /**
     * Console object
     * @var object
     */
    private static $_oInstance = null;
    
    /**
     * set constructor
     */
    public function __construct () {
        set_error_handler( array( $this, 'setErrorHandle' ) );
    }
    
    /**
     * get a valid instance of this class
     * @return object
     */
    public static function getInstance() {
        if( is_null(self::$_oInstance) ) {
            $c = __CLASS__;
            self::$_oInstance = new $c;
        }
        return self::$_oInstance;
    }
    
    /**
     * set error handler
     * @param int $err
     * @param string $message
     * @param string $file
     * @param string $line
     */
    public function setErrorHandle($err, $message, $file, $line) {
        $oJsonRpc = JsonRpc::getInstance();
        $content = explode("\n", file_get_contents($file));
        header('Content-Type: application/json');
        $id = $oJsonRpc->extractId();
        $error = array(
           "code" => 100,
           "message" => "Server error",
           "error" => array(
              "name" => "PHPErorr",
              "code" => $err,
              "message" => $message,
              "file" => $file,
              "at" => $line,
              "line" => $content[$line-1]));
        ob_end_clean();
        echo $oJsonRpc->response(null, $id, $error);
        exit();
    }
    
    /**
     * check if field exists
     * @param object $object
     * @param string $field
     * @return boolean
     */
    private function hasField($object, $field) {
        return array_key_exists($field, get_object_vars($object));
    }
    
    /**
     * get field
     * @param object $object
     * @param string $field
     * @param string $default
     * @return string
     */
    private function getField($object, $field, $default) {
        $array = get_object_vars($object);
        if (isset($array[$field])) {
            return $array[$field];
        } else {
            return $default;
        }
    }
        
    /**
     * returns last JSON Error
     * @return string
     */
    private function getJsonError() {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return 'No error has occurred';
            case JSON_ERROR_DEPTH:
                return 'The maximum stack depth has been exceeded';
            case JSON_ERROR_CTRL_CHAR:
                return 'Control character error, possibly incorrectly encoded';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
        }
    }
    
    /**
     * returns raw post data
     * @return array
     */
    private function getRawPostData() {
        return isset($GLOBALS['HTTP_RAW_POST_DATA']) ? 
                    $GLOBALS['HTTP_RAW_POST_DATA'] : 
                    file_get_contents('php://input');
    }
    
    /**
     * make the response
     * @param string $result
     * @param int $id
     * @param string $error
     * @return json string
     */
    public function response($result, $id, $error) {
        if ($error) { 
            $error['name'] = 'JSONRPCError';
        }
        return json_encode(array("jsonrpc" => "2.0",
                                 'result' => $result,
                                 'id' => $id,
                                 'error'=> $error));
    }
    
    /**
     * try to extract id from broken json
     * @return int
     */
    public function extractId() {
        $regex = '/[\'"]id[\'"] *: *([0-9]*)/';
        $rawData = $this->getRawPostData();
        if (preg_match($regex, $rawData, $m)) {
            return intval($m[1]);
        } else {
            return null;
        }
    }
    
    /**
     * get the current URL
     * @return string
     */
    private function currentURL() {
        $pageURL = 'http';
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
        }
        return $pageURL;
    }
    
    /**
     * get service description of the class
     * @param object $object
     * @return array
     */
    private function serviceDescription($object) {
        $class_name = get_class($object);
        $methods = get_class_methods($class_name);
        $service = ["sdversion" => "1.0",
                    "name" => "DemoService",
                    "address" => $this->currentURL(),
                    "id" => "urn:md5:" . md5($this->currentURL())];
        $static = get_class_vars($class_name);
        foreach ($methods as $method_name) {
            $proc = array("name" => $method_name);
            $method = new \ReflectionMethod($class_name, $method_name);
            $params = array();
            foreach ($method->getParameters() as $param) {
                $params[] = $param->name;
            }
            $proc['params'] = $params;
            $help_str_name = $method_name . "_documentation";
            if (array_key_exists($help_str_name, $static)) {
                $proc['help'] = $static[$help_str_name];
            }
            $service['procs'][] = $proc;
        }
        return $service;
    }
    
    /**
     * get the json request
     * @return array
     * @throws JsonRpcExeption
     */
    private function getJsonRequest() {
        $request = $this->getRawPostData();
        if ($request == "") {
            throw new JsonRpcExeption(101, "Parse Error: no data");
        }
        $encoding = mb_detect_encoding($request, 'auto');
        if ($encoding != 'UTF-8') {
            $request = iconv($encoding, 'UTF-8', $request);
        }
        $request = json_decode($request);
        if ($request == NULL) { // parse error
            $error = $this->getJsonError();
            throw new JsonRpcExeption(101, "Parse Error: $error");
        }
        return $request;
    }
    
    /**
     * create json rpc request
     * @param object $object
     * @throws JsonRpcExeption
     */
    public function handleJsonRpc($object) {
        try {
            $input  = $this->getJsonRequest();
            $method = $this->getField($input, 'method', null);
            $params = $this->getField($input, 'params', null);
            $id     = intval($this->getField($input, 'id', null));
            // json rpc error
            if (!($method && $id)) {
                if (!$id) {
                    $id = $this->extractId();
                }
                if (!$method) {
                    $error = "no method";
                } else if (!$id) {
                    $error = "no id";
                } else {
                    $error = "unknown reason";
                }
                throw new JsonRpcExeption(103,  "Invalid Request: $error");
            }
            // fix params (if params is null set it to empty array)
            $params = $params ?? [];
            
            // if params is object change it to array
            if (is_object($params)) {
                if (count(get_object_vars($params)) == 0) {
                    $params = [];
                } else {
                    $params = get_object_vars($params);
                }
            }
            
            // call Service Method
            $class = get_class($object);
            $methods = get_class_methods($class);
            if (strcmp($method, "system.describe") == 0) {
                echo json_encode($this->serviceDescription($object));
            } else if (!in_array($method, $methods) && !in_array("__call", $methods)) {
                // __call will be called for any method that's missing
                $msg = "Procedure `" . $method . "' not found";
                throw new JsonRpcExeption(104, $msg);
            } else {
                if (in_array("__call", $methods) && !in_array($method, $methods)) {
                    $result = call_user_func_array(array($object, $method), $params);
                    echo response($result, $id, null);
                } else {
                    $method_object = new \ReflectionMethod($class, $method);
                    $num_got = count($params);
                    $num_expect = $method_object->getNumberOfParameters();
                    if ($num_got != $num_expect) {
                        $msg = "Wrong number of parameters. Got $num_got expect $num_expect";
                        throw new JsonRpcExeption(105, $msg);
                    } else {
                        try {
                            $result = call_user_func_array(array($object, $method), $params);
                            echo $this->response($result, $id, null);
                        } catch (\Exception $e) {
                            throw new JsonRpcExeption(105, $e->getMessage());
                        }
                    }
                }
            }
        } catch (JsonRpcExeption $e) {
            // exteption with error code
            $msg = $e->getMessage();
            $code = $e->code();
            if ($code = 101) { // parse error;
                $id = $this->extractId();
            }
            echo $this->response(null, $id, array("code"=>$code, "message"=>$msg));
        } catch (Exception $e) {
            //catch all exeption from user code
            $msg = $e->getMessage();
            echo $this->response(null, $id, array("code"=>200, "message"=>$msg));
        }
        ob_end_flush();
    }
    
}
