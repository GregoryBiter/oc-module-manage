<?php

namespace Tests\Unit\Services;

use Ocm\Services\DatabaseService;
use PHPUnit\Framework\TestCase;

class DatabaseServiceTest extends TestCase {
    private $testDir;
    private $dbService;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_db_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
        $this->dbService = new DatabaseService();
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

    public function testGetCredentialsFromStandardConfig(): void {
        $configContent = <<<'PHP'
<?php
define('DB_DRIVER', 'mpdo');
define('DB_HOSTNAME', '127.0.0.1');
define('DB_USERNAME', 'oc_user');
define('DB_PASSWORD', 'secret123');
define('DB_DATABASE', 'opencart_store');
define('DB_PORT', '3306');
define('DB_PREFIX', 'oc_');
PHP;
        file_put_contents($this->testDir . '/config.php', $configContent);

        $creds = $this->dbService->getCredentials($this->testDir);

        $this->assertNotNull($creds);
        $this->assertSame('mpdo', $creds['driver']);
        $this->assertSame('127.0.0.1', $creds['hostname']);
        $this->assertSame('oc_user', $creds['username']);
        $this->assertSame('secret123', $creds['password']);
        $this->assertSame('opencart_store', $creds['database']);
        $this->assertSame(3306, $creds['port']);
        $this->assertSame('oc_', $creds['prefix']);
    }

    public function testGetCredentialsReturnsNullIfConfigMissing(): void {
        $creds = $this->dbService->getCredentials($this->testDir);
        $this->assertNull($creds);
    }
}
