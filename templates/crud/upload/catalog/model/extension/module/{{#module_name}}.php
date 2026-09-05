<?php
class ModelExtensionModule{{#ModuleName}} extends Model {
    public function getItems() {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "{{#module_name}}` WHERE `status` = '1' ORDER BY `sort_order` ASC");
        return $query->rows;
    }

    public function getItem($id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "{{#module_name}}` WHERE `{{#module_name}}_id` = '" . (int)$id . "' AND `status` = '1'");
        return $query->row;
    }
}
