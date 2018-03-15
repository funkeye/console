<?PHP 
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

use pinzweb\Console\JsonRpc;

class Console {
    /**
     * JsonRpc object
     * @var object
     */
    protected $server = NULL;
    
    /**
     * Console object
     * @var object
     */
    private static $_oInstance = null;
    
        
    /**
     * set constructor
     */
    public function __construct() {
        ini_set('display_errors', 1);
        ini_set('track_errors', 1);
        if (function_exists('xdebug_disable')) {
            xdebug_disable();
        }
        $this->server = JsonRpc::getInstance();         
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
     * initialize Json RPC Server
     * @param object $o
     */
    public function init($o) {
        $this->server->handleJsonRpc($o);        
    }
    
}