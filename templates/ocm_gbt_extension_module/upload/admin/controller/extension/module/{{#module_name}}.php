<?php
use GDT\ToolsController;
class ControllerExtensionModule{{#NameModule}} extends Controller {
  use ToolsController;
  
  private $m_code = '{{#module_name}}';
  private $m_route = 'extension/module/{{#module_name}}';
  private $m_set = 'module_{{#module_name}}';
  private $m_events = [];

  private $error = array();

  public function index() {
    $this->load->language($this->m_route);

    $this->document->setTitle($this->language->get('heading_title'));

    $this->load->model('setting/setting');

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
      $this->model_setting_setting->editSetting($this->m_set, $this->request->post);

      $this->session->data['success'] = $this->language->get('text_success');

      $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
    }

    if (isset($this->error['warning'])) {
      $data['error_warning'] = $this->error['warning'];
    } else {
      $data['error_warning'] = '';
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
      'href' => $this->url->link($this->m_route, 'user_token=' . $this->session->data['user_token'], true)
    );

    $data['action'] = $this->url->link($this->m_route, 'user_token=' . $this->session->data['user_token'], true);

    $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);


    $data = array_merge($data, $this->getSetting([
      $this->m_set . '_status' => 1,
    ]));

    $data['module'] = [
      'name' => $this->m_code,
      'route' => $this->m_route,
      'set' => $this->m_set,
      'events' => $this->m_events,
    ];

    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    $this->response->setOutput($this->load->view($this->m_route, $data));
  }

  protected function validate() {
    if (!$this->user->hasPermission('modify', $this->m_route)) {
      $this->error['warning'] = $this->language->get('error_permission');
    }

    return !$this->error;
  }
}