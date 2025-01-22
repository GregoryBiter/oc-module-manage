<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerExtensionModule{{#ModuleName}} extends Controller {
	private $error = array();

	private $module_name = 'extension/module/{{#module_name}}';
	private $module_onli_name = '{{#module_name}}';

	public function index() {
		$this->load->language($this->module_name);

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_'.$this->module_onli_name, $this->request->post);

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
			'href' => $this->url->link($this->module_name, 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link($this->module_name, 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_'.$this->module_onli_name.'_status'])) {
			$data['module_'.$this->module_onli_name.'_status'] = $this->request->post['module_'.$this->module_onli_name.'_status'];
		} else {
			$data['module_'.$this->module_onli_name.'_status'] = $this->config->get('module_'.$this->module_onli_name.'_status');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->module_name, $data));
	}

	public function install() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_'.$this->module_onli_name, ['module_'.$this->module_onli_name.'_status' => 1]);
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('module_'.$this->module_onli_name);
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', $this->module_name)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}