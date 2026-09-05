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
}
