<?php

$bootstrapPath = DIR_SYSTEM . 'library/laravel_orm/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    die('Ошибка: Необходимо установить модуль laravel_orm. Файл ' . $bootstrapPath . ' не найден.');
}
require $bootstrapPath;

use Illuminate\Database\Capsule\Manager as Capsule;
class ModelExtensionModuleMyModule extends Model {

    public function install() {
        if (!Capsule::schema()->hasTable('{{#module_name}}')) {
            Capsule::schema()->create('{{#module_name}}', function ($table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->text('description');
            });
        }
    }
    public function uninstall() {
        Capsule::schema()->dropIfExists('{{#module_name}}');
    }

    public function addProduct($data) {
        Capsule::table('{{#module_name}}')->insert($data);
    }

    public function getRecords() {
        return Capsule::table('{{#module_name}}')->get();
    }

    public function getRecord($id) {
        return Capsule::table('{{#module_name}}')->where('id', $id)->first();
    }

    public function updateRecord($id, $data) {
        Capsule::table('{{#module_name}}')->where('id', $id)->update($data);
    }

    public function deleteRecord($id) {
        Capsule::table('{{#module_name}}')->where('id', $id)->delete();
    }
}