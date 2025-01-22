
<?php
class ControllerExtensionModule{{#ModuleName}} extends Controller {
	private $module_name = "{{#module_name}}";
	public function index() {
		if ($this->config->get('module_'.$this->module_name.'_status')) {
			// Ваш код для отображения модуля в каталоге
		}
	}
}