<?php
namespace OcModule;
class Registry {

    private $config = [];

    public function get($key){
        return $this->config[$key];
    }
    public function set($key, $value){
        $this->config[$key] = $value;
    }
}