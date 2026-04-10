<?php

namespace Tests\Unit\Services;

use Ocm\Services\FileSystemService;
use PHPUnit\Framework\TestCase;

class FileSystemServiceTest extends TestCase {
    private $testDir;
    private $fs;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_fs_test_' . uniqid();
        mkdir($this->testDir);
        mkdir($this->testDir . '/module');
        mkdir($this->testDir . '/opencart');
        $this->fs = new FileSystemService($this->testDir . '/module', $this->testDir . '/opencart');
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

    public function testFindAllFiles() {
        mkdir($this->testDir . '/module/subdir');
        file_put_contents($this->testDir . '/module/file1.php', 'test');
        file_put_contents($this->testDir . '/module/subdir/file2.php', 'test');

        $files = $this->fs->findAllFiles($this->testDir . '/module');
        sort($files);
        $this->assertEquals(['file1.php', 'subdir/file2.php'], $files);
    }

    public function testCopyDirRecursively() {
        mkdir($this->testDir . '/module/subdir');
        file_put_contents($this->testDir . '/module/{{#module_name}}.php', 'Hello {{#ModuleName}}');
        
        $placeholders = [
            '{{#module_name}}' => 'test_mo',
            '{{#ModuleName}}' => 'TestMo'
        ];

        $this->fs->copyDirRecursively($this->testDir . '/module', $this->testDir . '/target', $placeholders);

        $this->assertTrue(file_exists($this->testDir . '/target/test_mo.php'));
        $this->assertEquals('Hello TestMo', file_get_contents($this->testDir . '/target/test_mo.php'));
    }

    public function testSyncFile() {
        file_put_contents($this->testDir . '/module/sync.php', 'test');
        
        $mtimes = [];
        $result = $this->fs->syncFile('sync.php', $mtimes);
        
        $this->assertTrue($result);
        $this->assertTrue(file_exists($this->testDir . '/opencart/sync.php'));
        $this->assertArrayHasKey('sync.php', $mtimes);
    }

    public function testRemoveFile() {
        mkdir($this->testDir . '/opencart/subdir', 0777, true);
        file_put_contents($this->testDir . '/opencart/subdir/del.php', 'test');
        
        $result = $this->fs->removeFile('subdir/del.php');
        
        $this->assertTrue($result);
        $this->assertFalse(file_exists($this->testDir . '/opencart/subdir/del.php'));
        $this->assertFalse(is_dir($this->testDir . '/opencart/subdir')); // Empty dir should be removed
    }
}
