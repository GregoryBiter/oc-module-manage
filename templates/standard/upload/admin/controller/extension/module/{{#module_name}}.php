<?php
class ControllerExtensionModule{{#ModuleName}} extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/{{#module_name}}');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_{{#module_name}}', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $status = isset($this->request->post['module_{{#module_name}}_status']) ? $this->request->post['module_{{#module_name}}_status'] : $this->config->get('module_{{#module_name}}_status');
        $data['module_{{#module_name}}_status'] = $status;
        $data['status'] = $status;

        $title = isset($this->request->post['module_{{#module_name}}_title']) ? $this->request->post['module_{{#module_name}}_title'] : (array)$this->config->get('module_{{#module_name}}_title');
        $data['module_{{#module_name}}_title'] = $title;
        $data['module_title'] = $title;

        $description = isset($this->request->post['module_{{#module_name}}_description']) ? $this->request->post['module_{{#module_name}}_description'] : (array)$this->config->get('module_{{#module_name}}_description');
        $data['module_{{#module_name}}_description'] = $description;
        $data['module_description'] = $description;

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/{{#module_name}}', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/{{#module_name}}')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
