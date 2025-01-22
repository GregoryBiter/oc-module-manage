<?php
namespace OcModule;
class Factory {

    private $config = [];

    public function __construct(\App\Registry $registry) {
        $this->registry = $registry;

    }

    public function __get($key){
        return $this->registry->get($key);
    }

    public function __set($key, $value){
        $this->registry->set($key, $value);
    }
}

