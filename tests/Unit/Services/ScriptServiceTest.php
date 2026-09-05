<?php

namespace Tests\Unit\Services;

use Ocm\Services\FileSystemService;
use Ocm\Services\ScriptService;
use PHPUnit\Framework\TestCase;

class ScriptServiceTest extends TestCase {
    private $testDir;
    private $scriptService;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_script_test_' . uniqid();
        mkdir($this->testDir . '/builtin_scripts', 0777, true);

        file_put_contents($this->testDir . '/builtin_scripts/sample.sh', "#!/bin/bash\necho \"Sample output: $1\"\n");
        chmod($this->testDir . '/builtin_scripts/sample.sh', 0755);

        file_put_contents($this->testDir . '/builtin_scripts/sample_php.php', "<?php echo 'PHP output';\n");

        $fs = new FileSystemService();
        $this->scriptService = new ScriptService($fs, $this->testDir . '/builtin_scripts');
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

    public function testGetAvailableScriptsIncludesBuiltin(): void {
        $scripts = $this->scriptService->getAvailableScripts($this->testDir);
        $this->assertArrayHasKey('sample', $scripts);
        $this->assertSame('builtin', $scripts['sample']['type']);
        $this->assertSame('sh', $scripts['sample']['extension']);
    }

    public function testLocalScriptOverridesBuiltin(): void {
        $localScriptDir = $this->testDir . '/.ocm/scripts';
        mkdir($localScriptDir, 0777, true);
        file_put_contents($localScriptDir . '/sample.sh', "#!/bin/bash\necho override\n");

        $scripts = $this->scriptService->getAvailableScripts($this->testDir);
        $this->assertArrayHasKey('sample', $scripts);
        $this->assertSame('local', $scripts['sample']['type']);
    }

    public function testResolveScriptPath(): void {
        $path = $this->scriptService->resolveScriptPath('sample', $this->testDir);
        $this->assertNotNull($path);
        $this->assertFileExists($path);

        $pathWithExt = $this->scriptService->resolveScriptPath('sample.sh', $this->testDir);
        $this->assertSame($path, $pathWithExt);

        $phpPath = $this->scriptService->resolveScriptPath('sample_php', $this->testDir);
        $this->assertNotNull($phpPath);
        $this->assertStringEndsWith('sample_php.php', $phpPath);

        $missing = $this->scriptService->resolveScriptPath('non_existing', $this->testDir);
        $this->assertNull($missing);
    }

    public function testExecuteScript(): void {
        $path = $this->scriptService->resolveScriptPath('sample', $this->testDir);
        ob_start();
        $exitCode = $this->scriptService->execute($path, ['hello']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Sample output: hello', $output);
    }
}
