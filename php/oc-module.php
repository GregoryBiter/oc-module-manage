<?php
//autoload function

function autoload($class){
    $class = str_replace('\\', '/', $class);
    require_once __DIR__ . '/' . $class . '.php';
}

spl_autoload_register('autoload');

require_once 'vendor/autoload.php';

define('OC_MODULE_DIR', __DIR__);
define('OC_MODULE_TEMPLATES_DIR', OC_MODULE_DIR . '/templates');
define('OC_MODULE_JSON_FILE', getcwd() . '/opencart-module.json');
define('OC_MODULE_MODULE_DIR', getcwd() . '/upload');


$SCRIPT_DIR = __DIR__;
$CURRENT_DIR = getcwd();
$MODULE_DIR = $CURRENT_DIR . '/upload';
$JSON_FILE = $CURRENT_DIR . '/opencart-module.json';
$TEMPLATES_DIR = $SCRIPT_DIR . '/templates';

function to_camel_case($snake_str) {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $snake_str)));
}

function to_camel_case_lower($snake_str) {
    $camel = to_camel_case($snake_str);
    return lcfirst($camel);
}

function get_opencart_dirs($data) {
    global $CURRENT_DIR;
    return isset($data['opencart_dirs']) ? $data['opencart_dirs'] : [$CURRENT_DIR . '/../..'];
}



$regisry = new \OcModule\Registry();
$opencart = new \OcModule\Opencart();
$opencart->console();

