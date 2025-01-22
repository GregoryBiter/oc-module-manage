<?php
namespace OcModule;

class Console extends Factory {

    private $config = [];

    public function __construct() {
    }

    public static function getInput($message){
        return readline($message);
    }
    public static function render($message){
        echo $message;
    }
}