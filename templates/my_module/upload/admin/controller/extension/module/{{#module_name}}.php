<?php
require_once DIR_APPLICATION . 'controller/extension/module/{{#module_name}}_helper/install.php';
require_once DIR_APPLICATION . 'controller/extension/module/{{#module_name}}_helper/template.php';

class ControllerExtensionModuleMyModule extends Controller {
    use GBMyModuleInstall, GBMyModuleTemplates;

    private $module_name = '{{#module_name}}';
    private $module_path_full = 'extension/module/{{#module_name}}';
    private $error = array();

    private $settings_fields = [
        'status' => [
            'type' => 'select',
            'options' => ['enabled', 'disabled'],
            'default' => 'enabled',
            'name' => 'entry_status'
        ],
        'custom_text' => [
            'type' => 'text',
            'default' => '',
            'name' => 'entry_custom_text'
        ]
    ];

    public function __construct($registry) {
        parent::__construct($registry);
        $this->getEventList();
    }

    public function index() {
        $this->loadLanguageAndModel();

        if ($this->isPostRequest() && $this->validate()) {
            $this->saveSettings();
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->getAdminLink('marketplace/extension', ['type' => 'module']));
        }

        $data = $this->getCommonData();
        $data['records'] = $this->{$this->getModelName()}->getRecords();
        $data['settings'] = $this->loadSettings();

        $this->response->setOutput($this->load->view($this->module_path_full, $data));
    }

    public function add() {
        $this->loadLanguageAndModel();

        if ($this->isPostRequest() && $this->validateForm()) {
            $this->{$this->getModelName()}->addProduct($this->request->post);
            $this->session->data['success'] = $this->language->get('text_add_success');
            $this->response->redirect($this->getAdminLink($this->module_path_full));
        }

        $this->index();
    }

    public function edit() {
        $this->loadLanguageAndModel();

        if ($this->isPostRequest() && $this->validateForm()) {
            $id = $this->request->post['id'];
            $data = [
                'name' => $this->request->post['name'] ?? '',
                'description' => $this->request->post['description'] ?? ''
            ];
            $this->{$this->getModelName()}->updateRecord($id, $data);
            $this->session->data['success'] = $this->language->get('text_edit_success');
            $this->response->redirect($this->getAdminLink($this->module_path_full));
        } else {
            $this->loadEditForm();
        }
    }

    public function delete() {
        $this->loadLanguageAndModel();

        if (isset($this->request->post['id']) && $this->validate()) {
            $this->{$this->getModelName()}->deleteRecord($this->request->post['id']);
            $this->session->data['success'] = $this->language->get('text_delete_success');
            $this->response->redirect($this->getAdminLink($this->module_path_full));
        }

        $this->index();
    }

    public function resetEvents() {
        $this->loadLanguageAndModel();

        $this->uninstallEvent();
        $this->installEvent();

        $this->session->data['success'] = $this->language->get('text_reset_success');
        $this->response->redirect($this->getAdminLink($this->module_path_full));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', $this->module_path_full)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    protected function validateForm() {
        if (empty($this->request->post['name'])) {
            $this->error['name'] = 'Name is required!';
        }

        if (empty($this->request->post['description'])) {
            $this->error['description'] = 'Description is required!';
        }

        return !$this->error;
    }

    private function getAdminLink($route, $params = []) {
        $params['user_token'] = $this->session->data['user_token'];
        return $this->url->link($route, http_build_query($params), true);
    }

    private function getCatalogLink($route, $params = []) {
        return $this->url->link($route, http_build_query($params), true);
    }

    private function isPostRequest() {
        return $this->request->server['REQUEST_METHOD'] == 'POST';
    }

    private function saveSettings() {
        $this->load->model('setting/setting');
        $settings = [];
        foreach ($this->settings_fields as $field => $config) {
            $settings[$field] = $this->request->post[$field] ?? $config['default'];
        }
        $this->model_setting_setting->editSetting($this->module_name, $settings);
    }

    private function loadSettings() {
        $settings = [];
        foreach ($this->settings_fields as $field => $config) {
            $settings[$field] = $this->config->get($this->module_name . '_' . $field) ?? $config['default'];
        }
        return $settings;
    }

    private function getCommonData() {
        $data = [
            'heading_title' => $this->language->get('heading_title'),
            'action' => $this->getAdminLink($this->module_path_full),
            'cancel' => $this->getAdminLink('marketplace/extension', ['type' => 'module']),
            'add_action' => $this->getAdminLink($this->module_path_full . '/add'),
            'edit_action' => $this->getAdminLink($this->module_path_full . '/edit'),
            'delete_action' => $this->getAdminLink($this->module_path_full . '/delete'),
            'reset_events_action' => $this->getAdminLink($this->module_path_full . '/resetEvents'),
            'user_token' => $this->session->data['user_token'],
            'header' => $this->load->controller('common/header'),
            'column_left' => $this->load->controller('common/column_left'),
            'footer' => $this->load->controller('common/footer'),
            'error_warning' => $this->error['warning'] ?? '',
            'error_name' => $this->error['name'] ?? '',
            'error_description' => $this->error['description'] ?? '',
            $this->module_name . '_status' => $this->request->post[$this->module_name . '_status'] ?? $this->config->get($this->module_name . '_status'),
            'module_name' => $this->module_name,
            'settings_fields' => $this->settings_fields
        ];

        foreach ($this->settings_fields as $field => $config) {
            $data[$field] = $this->request->post[$field] ?? $this->config->get($this->module_name . '_' . $field) ?? $config['default'];
        }

        return $data;
    }

    private function loadEditForm() {
        $id = $this->request->get['id'] ?? 0;
        $record = $this->{$this->getModelName()}->getRecord($id);
        $data = $this->getCommonData();
        $data['record'] = $record;
        $data['action'] = $this->getAdminLink($this->module_path_full . '/edit');
        $data['cancel'] = $this->getAdminLink($this->module_path_full);

        $this->response->setOutput($this->load->view($this->module_path_full . '_form', $data));
    }

    private function getModelName() {
        return 'model_' . str_replace('/', '_', $this->module_path_full);
    }

    private function loadLanguageAndModel() {
        $this->load->language($this->module_path_full);
        $this->load->model($this->module_path_full);
    }
}
