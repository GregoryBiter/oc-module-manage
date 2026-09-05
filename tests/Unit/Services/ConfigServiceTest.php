<?php

namespace Tests\Unit\Services;

use Ocm\Services\ConfigService;
use PHPUnit\Framework\TestCase;

class ConfigServiceTest extends TestCase {
    private $testDir;
    private $config;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_test_' . uniqid();
        mkdir($this->testDir);
        $this->config = new ConfigService($this->testDir);
    }

    protected function tearDown(): void {
        $this->removeDir($this->testDir);
    }

    private function removeDir($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testValidateMetadata() {
        $valid = ['module_name' => 'Test', 'code' => 'test'];
        $errors = [];
        $this->assertTrue($this->config->validateMetadata($valid, $errors));

        $invalid = ['files' => []];
        $this->assertFalse($this->config->validateMetadata($invalid, $errors));
        $this->assertContains("Поле 'files' запрещено в opencart-module.json (используйте .ocm_files.json)", $errors);
    }

    public function testInferCode() {
        $metadata = ['controller' => 'extension/module/test_module'];
        $this->assertEquals('module_test_module', $this->config->inferCode($metadata));

        $metadata = ['type' => 'payment', 'name' => 'stripe'];
        $this->assertEquals('payment_stripe', $this->config->inferCode($metadata));
    }

    public function testToCamelCase() {
        $this->assertEquals('MyModule', $this->config->toCamelCase('my_module'));
        $this->assertEquals('MyModule', $this->config->toCamelCase('my_module_'));
    }

    public function testToCamelCaseLower() {
        $this->assertEquals('myModule', $this->config->toCamelCaseLower('my_module'));
    }

    public function testMatchWildcardPattern() {
        $this->assertTrue($this->config->matchWildcardPattern('*.php', 'test.php'));
        $this->assertTrue($this->config->matchWildcardPattern('admin/view/**/test.twig', 'admin/view/template/extension/test.twig'));
        $this->assertFalse($this->config->matchWildcardPattern('admin/*.php', 'catalog/test.php'));
    }

    public function testFindsAndParsesIndexXml() {
        $xmlContent = '<?xml version="1.0" encoding="utf-8"?><modification><code>my_ocmod_code</code><name>My OCMOD Name</name><version>2.1.0</version><author>Author Name</author></modification>';
        file_put_contents($this->testDir . '/index.xml', $xmlContent);

        $this->assertSame($this->testDir . '/index.xml', $this->config->getOcmodFilePath());
        $this->assertSame('index.xml', $this->config->getOcmodFileName());

        $parsed = $this->config->parseInstallXmlMetadata();
        $this->assertNotNull($parsed);
        $this->assertSame('index.xml', $parsed['file_name']);
        $this->assertSame('my_ocmod_code', $parsed['code']);
        $this->assertSame('My OCMOD Name', $parsed['name']);
        $this->assertSame('2.1.0', $parsed['version']);
    }

    public function testInstallXmlTakesPriorityOverIndexXml() {
        file_put_contents($this->testDir . '/install.xml', '<modification><code>install_code</code></modification>');
        file_put_contents($this->testDir . '/index.xml', '<modification><code>index_code</code></modification>');

        $this->assertSame($this->testDir . '/install.xml', $this->config->getOcmodFilePath());
        $this->assertSame('install.xml', $this->config->getOcmodFileName());
    }
}
