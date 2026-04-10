<?php

namespace Tests\Integration\Commands;

use Ocm\Base\Application;
use Ocm\Base\Input;
use Ocm\Base\Output;
use PHPUnit\Framework\TestCase;

abstract class CommandTestCase extends TestCase {
    protected $app;
    protected $output;
    protected $testDir;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_cmd_test_' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        $this->app = new Application();
        // Overwrite default services with ones using testDir
        $fileSystem = new \Ocm\Services\FileSystemService($this->testDir . '/upload', $this->testDir . '/opencart');
        $config = new \Ocm\Services\ConfigService($this->testDir);
        
        // Mock DB connection if needed, but for now we skip DB-heavy tests or use a mock
        $openCart = $this->createMock(\Ocm\Services\OpenCartService::class);
        $module = new \Ocm\Services\ModuleService($fileSystem, $config, $openCart);

        // Inject services into app
        $ref = new \ReflectionProperty($this->app, 'services');
        $ref->setAccessible(true);
        $ref->setValue($this->app, [
            'filesystem' => $fileSystem,
            'config' => $config,
            'opencart' => $openCart,
            'module' => $module
        ]);

        $this->output = $this->createMock(Output::class);
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
}
