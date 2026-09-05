<?php

namespace Tests\Unit\Services;

use Ocm\Services\FileSystemService;
use Ocm\Services\TemplateService;
use PHPUnit\Framework\TestCase;

class TemplateServiceTest extends TestCase {
    private $testDir;
    private $templateService;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_tpl_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
        mkdir($this->testDir . '/builtin_tpl/sample_tpl', 0777, true);
        file_put_contents($this->testDir . '/builtin_tpl/sample_tpl/{{#module_name}}.txt', 'Name: {{#ModuleName}}');

        $fs = new FileSystemService();
        $this->templateService = new TemplateService($fs, $this->testDir . '/builtin_tpl');
    }

    protected function tearDown(): void {
        $this->removeDir($this->testDir);
    }

    private function removeDir($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGetAvailableTemplatesIncludesBuiltin(): void {
        $templates = $this->templateService->getAvailableTemplates($this->testDir);
        $this->assertArrayHasKey('sample_tpl', $templates);
        $this->assertSame('builtin', $templates['sample_tpl']['type']);
    }

    public function testLocalTemplateOverridesBuiltin(): void {
        $localTplDir = $this->testDir . '/.ocm/templates/sample_tpl';
        mkdir($localTplDir, 0777, true);

        $templates = $this->templateService->getAvailableTemplates($this->testDir);
        $this->assertArrayHasKey('sample_tpl', $templates);
        $this->assertSame('local', $templates['sample_tpl']['type']);
    }

    public function testCreateFromTemplate(): void {
        $targetDir = $this->testDir . '/created_module';
        $placeholders = [
            '{{#module_name}}' => 'hello_world',
            '{{#ModuleName}}' => 'HelloWorld'
        ];

        $result = $this->templateService->createFromTemplate('sample_tpl', $targetDir, $placeholders, $this->testDir);
        $this->assertTrue($result);
        $this->assertFileExists($targetDir . '/hello_world.txt');
        $this->assertSame('Name: HelloWorld', file_get_contents($targetDir . '/hello_world.txt'));
    }

    public function testDefaultBuiltinTemplatesExist(): void {
        $defaultService = new TemplateService(new FileSystemService());
        $templates = $defaultService->getAvailableTemplates();

        $this->assertArrayHasKey('standard', $templates);
        $this->assertArrayHasKey('crud', $templates);
        $this->assertArrayHasKey('ocmod', $templates);

        // standard should be first in preferred order
        $keys = array_keys($templates);
        $this->assertSame('standard', $keys[0]);
    }

    public function testTemplateAliasResolution(): void {
        $defaultService = new TemplateService(new FileSystemService());

        $standardPath = $defaultService->getTemplatePath('standard');
        $this->assertNotNull($standardPath);
        $this->assertSame($standardPath, $defaultService->getTemplatePath('ocm_gbt_extension_module'));
        $this->assertSame($standardPath, $defaultService->getTemplatePath('basic'));

        $crudPath = $defaultService->getTemplatePath('crud');
        $this->assertNotNull($crudPath);
        $this->assertSame($crudPath, $defaultService->getTemplatePath('my_module'));
        $this->assertSame($crudPath, $defaultService->getTemplatePath('advanced'));

        $ocmodPath = $defaultService->getTemplatePath('ocmod');
        $this->assertNotNull($ocmodPath);
        $this->assertSame($ocmodPath, $defaultService->getTemplatePath('modifier'));
    }

    public function testCreateModuleFromStandardTemplate(): void {
        $defaultService = new TemplateService(new FileSystemService());
        $targetDir = $this->testDir . '/new_standard_module';
        $placeholders = [
            '{{#module_name}}' => 'banner_slider',
            '{{#ModuleName}}' => 'BannerSlider',
            '{{#moduleName}}' => 'bannerSlider',
            '{{#NameModule}}' => 'BannerSlider',
            '{{#module_title}}' => 'Banner Slider',
            '{{#author}}' => 'Developer',
            '{{#version}}' => '1.0.0',
            '{{#year}}' => '2026',
            '{{#date}}' => '2026-09-05'
        ];

        $result = $defaultService->createFromTemplate('standard', $targetDir, $placeholders);
        $this->assertTrue($result);
        $this->assertFileExists($targetDir . '/opencart-module.json');
        $this->assertFileExists($targetDir . '/install.xml');
        $this->assertFileExists($targetDir . '/upload/admin/controller/extension/module/banner_slider.php');
        $this->assertFileExists($targetDir . '/upload/admin/view/template/extension/module/banner_slider.twig');
        $this->assertFileExists($targetDir . '/upload/admin/language/en-gb/extension/module/banner_slider.php');
        $this->assertFileExists($targetDir . '/upload/catalog/controller/extension/module/banner_slider.php');

        $adminCtrl = file_get_contents($targetDir . '/upload/admin/controller/extension/module/banner_slider.php');
        $this->assertStringContainsString('class ControllerExtensionModuleBannerSlider extends Controller', $adminCtrl);
        $this->assertStringContainsString('module_banner_slider', $adminCtrl);
    }
}
