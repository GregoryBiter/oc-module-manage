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

    public function testGetCredentialsFromEnvAndDockerSetup(): void {
        $parentEnv = dirname($this->testDir) . '/.env';
        $envContent = <<<'ENV'
MYSQL_HOST=db
MYSQL_PORT=3307
MYSQL_DATABASE=docker_opencart
MYSQL_USER=docker_user
MYSQL_PASSWORD=docker_pass

OC_DB_HOST=${MYSQL_HOST}
OC_DB_PORT=${MYSQL_PORT}
OC_DB_NAME=${MYSQL_DATABASE}
OC_DB_USER=${MYSQL_USER}
OC_DB_PASSWORD=${MYSQL_PASSWORD}
OC_DB_PREFIX=oc_
ENV;
        file_put_contents($parentEnv, $envContent);

        $configContent = <<<'PHP'
<?php
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', $_ENV['OC_DB_HOST']);
define('DB_USERNAME', $_ENV['OC_DB_USER']);
define('DB_PASSWORD', $_ENV['OC_DB_PASSWORD']);
define('DB_DATABASE', $_ENV['OC_DB_NAME']);
define('DB_PORT', $_ENV['OC_DB_PORT']);
define('DB_PREFIX', $_ENV['OC_DB_PREFIX']);
PHP;
        file_put_contents($this->testDir . '/config.php', $configContent);

        $creds = $this->dbService->getCredentials($this->testDir);

        @unlink($parentEnv);

        $this->assertNotNull($creds);
        $this->assertSame('mysqli', $creds['driver']);
        $this->assertSame('127.0.0.1', $creds['hostname']);
        $this->assertSame('docker_user', $creds['username']);
        $this->assertSame('docker_pass', $creds['password']);
        $this->assertSame('docker_opencart', $creds['database']);
        $this->assertSame(3307, $creds['port']);
        $this->assertSame('oc_', $creds['prefix']);
    }
}

