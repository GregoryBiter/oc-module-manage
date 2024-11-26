<?php
trait GBMyModuleInstall
{

    private $events;

    private function getEventList()
    {
        $this->events = [
            [
                'code' => $this->module_name . '_add_product',
                'trigger' => 'catalog/model/catalog/product/addProduct/after',
                'action' => $this->module_path . '/' . $this->module_name . '/module/my_module/afterAddProduct'
            ],
            [
                'code' => $this->module_name . '_delete_product',
                'trigger' => 'catalog/model/catalog/product/deleteProduct/after',
                'action' => $this->module_path . '/' . $this->module_name . '/afterDeleteProduct'
            ],
            [
                'code' => $this->module_name . '_add_menu_item',
                'trigger' => 'admin/view/common/column_left/before',
                'action' => $this->module_path . '/' . $this->module_name . '/addMenuItem'
            ]
        ];
    }

    private function installEvent()
    {
        $this->load->model('setting/event');
        foreach ($this->events as $event) {
            $this->model_setting_event->addEvent($event['code'], $event['trigger'], $event['action']);
        }
    }

    private function uninstallEvent()
    {
        $this->load->model('setting/event');
        foreach ($this->events as $event) {
            $this->model_setting_event->deleteEventByCode($event['code']);
        }
    }

    public function install()
    {
        $this->load->model('setting/event');
        $this->installDBMigration();
        $this->installEvent();
        $this->installTemplate();
    }

    private function installDBMigration()
    {
        $this->load->model($this->module_path_full);
        $this->{$this->module_path_full}->install();
    }

    public function uninstall()
    {
        $this->load->model('setting/event');
        foreach ($this->events as $event) {
            $this->model_setting_event->deleteEventByCode($event['code']);
        }
    }

    public function addMenuItem(&$route, &$data)
    {
        // if (!isset($data['menus']) || !is_array($data['menus'])) {
        //     return;
        // }
        if ($this->user->hasPermission('access', $this->module_path_full)) {
            $menu_item = [
                'name'     => 'My Module',
                'href'     => $this->getAdminLink($this->module_path_full),
                'children' => []
            ];

            $this->addMenuItemToExtensions($data, $menu_item);
        }

        if ($this->user->hasPermission('access', $this->module_path_full)) {
            $this->addMenuItemToSettings($data);
        }
    }


    private function addMenuItemToExtensions(&$data, $menu_item)
    {
        $inserted = false;

        foreach ($data['menus'] as &$menu) {
            if ($menu['id'] === 'menu-extension') {
                $menu['children'][] = $menu_item;
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $data['menus'][] = [
                'id'       => 'menu-extension',
                'icon'     => 'fa-puzzle-piece',
                'name'     => 'Extensions',
                'href'     => '',
                'children' => [$menu_item]
            ];
        }
    }

    private function addMenuItemToSettings(&$data)
    {
        $settings_menu_item = [
            'name'     => 'My Module Settings',
            'href'     => $this->getAdminLink($this->module_path_full . '/settings'),
            'children' => []
        ];

        $inserted = false;

        foreach ($data['menus'] as &$menu) {
            if ($menu['id'] === 'menu-system') {
                $menu['children'][] = $settings_menu_item;
                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $data['menus'][] = [
                'id'       => 'menu-system',
                'icon'     => 'fa-cog',
                'name'     => 'System',
                'href'     => '',
                'children' => [$settings_menu_item]
            ];
        }
    }

    public function afterAddProduct($route, $args, $output)
    {
        $this->log->write('Product added: ' . json_encode($args));
    }

    public function afterDeleteProduct($route, $args)
    {
        $this->log->write('Product deleted: ' . json_encode($args));
    }
}
