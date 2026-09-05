<?php
class ModelExtensionModule{{#ModuleName}} extends Model {
    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "{{#module_name}}` (
            `{{#module_name}}_id` INT(11) NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `status` TINYINT(1) NOT NULL DEFAULT '1',
            `sort_order` INT(3) NOT NULL DEFAULT '0',
            `date_added` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`{{#module_name}}_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "{{#module_name}}`");
    }

    public function addItem($data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "{{#module_name}}` SET 
            `title` = '" . $this->db->escape($data['title']) . "', 
            `description` = '" . $this->db->escape($data['description']) . "', 
            `status` = '" . (int)$data['status'] . "', 
            `sort_order` = '" . (int)$data['sort_order'] . "', 
            `date_added` = NOW()");

        return $this->db->getLastId();
    }

    public function editItem($id, $data) {
        $this->db->query("UPDATE `" . DB_PREFIX . "{{#module_name}}` SET 
            `title` = '" . $this->db->escape($data['title']) . "', 
            `description` = '" . $this->db->escape($data['description']) . "', 
            `status` = '" . (int)$data['status'] . "', 
            `sort_order` = '" . (int)$data['sort_order'] . "' 
            WHERE `{{#module_name}}_id` = '" . (int)$id . "'");
    }

    public function deleteItem($id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "{{#module_name}}` WHERE `{{#module_name}}_id` = '" . (int)$id . "'");
    }

    public function getItem($id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "{{#module_name}}` WHERE `{{#module_name}}_id` = '" . (int)$id . "'");
        return $query->row;
    }

    public function getItems($data = array()) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "{{#module_name}}`";

        $sort_data = array('title', 'status', 'sort_order', 'date_added');

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY `" . $data['sort'] . "`";
        } else {
            $sql .= " ORDER BY `sort_order`";
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function getTotalItems() {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "{{#module_name}}`");
        return (int)$query->row['total'];
    }
}
