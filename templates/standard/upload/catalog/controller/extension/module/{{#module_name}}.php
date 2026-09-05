<?php
class ControllerExtensionModule{{#ModuleName}} extends Controller {
    public function index($setting = []) {
        if (!$this->config->get('module_{{#module_name}}_status')) {
            return '';
        }

        $this->load->language('extension/module/{{#module_name}}');

        $language_id = (int)$this->config->get('config_language_id');

        $titles = (array)$this->config->get('module_{{#module_name}}_title');
        $data['title'] = !empty($titles[$language_id]) ? $titles[$language_id] : $this->language->get('heading_title');

        $descriptions = (array)$this->config->get('module_{{#module_name}}_description');
        $data['description'] = !empty($descriptions[$language_id]) ? html_entity_decode($descriptions[$language_id], ENT_QUOTES, 'UTF-8') : '';

        return $this->load->view('extension/module/{{#module_name}}', $data);
    }
}
