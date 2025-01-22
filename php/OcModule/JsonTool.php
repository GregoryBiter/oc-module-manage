<?php
namespace OcModule;
class JsonTool {

    private $config = [];

    public function load(){
        return $this->load_json();
    }
    public function save($data){
        $this->save_json($data);
    }
    private function load_json() {
        global $JSON_FILE;
        if (file_exists($JSON_FILE)) {
            return json_decode(file_get_contents($JSON_FILE), true);
        }
        return [];
    }

    private function save_json($data) {
        global $JSON_FILE;
        file_put_contents($JSON_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }

}