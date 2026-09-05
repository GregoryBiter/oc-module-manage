<?php
class ControllerExtensionModule{{#ModuleName}} extends Controller {
    public function index($setting = []) {
        if (!$this->config->get('module_{{#module_name}}_status')) {
            return '';
        }

        $this->load->language('extension/module/{{#module_name}}');
        $this->load->model('extension/module/{{#module_name}}');

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_no_results'] = $this->language->get('text_no_results');

        $items = $this->model_extension_module_{{#module_name}}->getItems();

        $data['items'] = array();
        foreach ($items as $item) {
            $data['items'][] = array(
                'id'          => $item['{{#module_name}}_id'],
                'title'       => $item['title'],
                'description' => html_entity_decode($item['description'], ENT_QUOTES, 'UTF-8')
            );
        }

        return $this->load->view('extension/module/{{#module_name}}', $data);
    }

    public function afterAddProduct($route, $args, $output) {
        $this->log->write('{{#module_name}} event: Product added ID ' . (isset($output) ? $output : ''));
    }
}
