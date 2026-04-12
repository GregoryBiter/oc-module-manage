<?php

namespace Tests\Unit\Base;

use Ocm\Base\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
    private $testDir;
    private $app;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_app_test_' . uniqid();
        mkdir($this->testDir);
        mkdir($this->testDir . '/scripts');

        if (!defined('SCRIPT_DIR')) {
            define('SCRIPT_DIR', $this->testDir);
        }

        $this->app = new class extends Application {
            public function resolveScript($script_name) {
                return $this->resolveExternalScriptPath($script_name);
            }
        };
    }

    protected function tearDown(): void {
        $this->removeDir($this->testDir);
    }

    private function removeDir($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testResolveExternalScriptPathWithExtension(): void {
        file_put_contents($this->testDir . '/scripts/test.sh', "#!/bin/bash\necho test\n");

        $this->assertSame(
            $this->testDir . '/scripts/test.sh',
            $this->app->resolveScript('test.sh')
        );
    }

    public function testResolveExternalScriptPathWithoutExtension(): void {
        file_put_contents($this->testDir . '/scripts/test.php', "<?php echo 'test';\n");

        $this->assertSame(
            $this->testDir . '/scripts/test.php',
            $this->app->resolveScript('test')
        );
    }

    public function testResolveExternalScriptPathReturnsNullForMissingScript(): void {
        $this->assertNull($this->app->resolveScript('missing'));
    }
}
