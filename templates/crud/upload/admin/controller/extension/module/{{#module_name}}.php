<?php
class ControllerExtensionModule{{#ModuleName}} extends Controller {
    private $error = array();

    public function install() {
        $this->load->model('extension/module/{{#module_name}}');
        $this->model_extension_module_{{#module_name}}->install();

        // Регистрация события (пример)
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent(
            '{{#module_name}}_event',
            'catalog/model/catalog/product/addProduct/after',
            'extension/module/{{#module_name}}/afterAddProduct'
        );
    }

    public function uninstall() {
        $this->load->model('extension/module/{{#module_name}}');
        $this->model_extension_module_{{#module_name}}->uninstall();

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('{{#module_name}}_event');
    }

    public function index() {
        $this->load->language('extension/module/{{#module_name}}');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/{{#module_name}}');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_{{#module_name}}', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $this->getList();
    }

    public function add() {
        $this->load->language('extension/module/{{#module_name}}');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/{{#module_name}}');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_{{#module_name}}->addItem($this->request->post);

            $this->session->data['success'] = $this->language->get('text_success_item');

            $this->response->redirect($this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm();
    }

    public function edit() {
        $this->load->language('extension/module/{{#module_name}}');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/{{#module_name}}');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_{{#module_name}}->editItem($this->request->get['{{#module_name}}_id'], $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success_item');

            $this->response->redirect($this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm();
    }

    public function delete() {
        $this->load->language('extension/module/{{#module_name}}');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/{{#module_name}}');

        if (isset($this->request->post['selected']) && $this->validate()) {
            foreach ($this->request->post['selected'] as $id) {
                $this->model_extension_module_{{#module_name}}->deleteItem($id);
            }

            $this->session->data['success'] = $this->language->get('text_success_delete');

            $this->response->redirect($this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getList();
    }

    protected function getList() {
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

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
        $data['add'] = $this->url->link('extension/module/{{#module_name}}/add', 'user_token=' . $this->session->data['user_token'], true);
        $data['delete'] = $this->url->link('extension/module/{{#module_name}}/delete', 'user_token=' . $this->session->data['user_token'], true);

        $filter_data = array(
            'start' => ($page - 1) * 20,
            'limit' => 20
        );

        $total_items = $this->model_extension_module_{{#module_name}}->getTotalItems();
        $results = $this->model_extension_module_{{#module_name}}->getItems($filter_data);

        $data['items'] = array();
        foreach ($results as $result) {
            $data['items'][] = array(
                'id'          => $result['{{#module_name}}_id'],
                'title'       => $result['title'],
                'status'      => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                'sort_order'  => $result['sort_order'],
                'date_added'  => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
                'edit'        => $this->url->link('extension/module/{{#module_name}}/edit', 'user_token=' . $this->session->data['user_token'] . '&{{#module_name}}_id=' . $result['{{#module_name}}_id'], true)
            );
        }

        $pagination = new Pagination();
        $pagination->total = $total_items;
        $pagination->page = $page;
        $pagination->limit = 20;
        $pagination->url = $this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($total_items) ? (($page - 1) * 20) + 1 : 0, ((($page - 1) * 20) > ($total_items - 20)) ? $total_items : ((($page - 1) * 20) + 20), $total_items, ceil($total_items / 20));

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

        if (isset($this->request->post['module_{{#module_name}}_status'])) {
            $data['module_{{#module_name}}_status'] = $this->request->post['module_{{#module_name}}_status'];
        } else {
            $data['module_{{#module_name}}_status'] = $this->config->get('module_{{#module_name}}_status');
        }

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/{{#module_name}}', $data));
    }

    protected function getForm() {
        $data['text_form'] = !isset($this->request->get['{{#module_name}}_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['title'])) {
            $data['error_title'] = $this->error['title'];
        } else {
            $data['error_title'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true)
        );

        if (!isset($this->request->get['{{#module_name}}_id'])) {
            $data['action'] = $this->url->link('extension/module/{{#module_name}}/add', 'user_token=' . $this->session->data['user_token'], true);
        } else {
            $data['action'] = $this->url->link('extension/module/{{#module_name}}/edit', 'user_token=' . $this->session->data['user_token'] . '&{{#module_name}}_id=' . $this->request->get['{{#module_name}}_id'], true);
        }

        $data['cancel'] = $this->url->link('extension/module/{{#module_name}}', 'user_token=' . $this->session->data['user_token'], true);

        if (isset($this->request->get['{{#module_name}}_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $item_info = $this->model_extension_module_{{#module_name}}->getItem($this->request->get['{{#module_name}}_id']);
        }

        if (isset($this->request->post['title'])) {
            $data['title'] = $this->request->post['title'];
        } elseif (!empty($item_info)) {
            $data['title'] = $item_info['title'];
        } else {
            $data['title'] = '';
        }

        if (isset($this->request->post['description'])) {
            $data['description'] = $this->request->post['description'];
        } elseif (!empty($item_info)) {
            $data['description'] = $item_info['description'];
        } else {
            $data['description'] = '';
        }

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($item_info)) {
            $data['status'] = $item_info['status'];
        } else {
            $data['status'] = 1;
        }

        if (isset($this->request->post['sort_order'])) {
            $data['sort_order'] = $this->request->post['sort_order'];
        } elseif (!empty($item_info)) {
            $data['sort_order'] = $item_info['sort_order'];
        } else {
            $data['sort_order'] = 0;
        }

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/{{#module_name}}_form', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/{{#module_name}}')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/{{#module_name}}')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['title']) < 1) || (utf8_strlen($this->request->post['title']) > 255)) {
            $this->error['title'] = $this->language->get('error_title');
        }

        return !$this->error;
    }
}
