<?php

namespace Tests\Unit\Services;

use Ocm\Services\AgentSkillService;
use Ocm\Services\FileSystemService;
use PHPUnit\Framework\TestCase;

class AgentSkillServiceTest extends TestCase {
    private $testDir;
    private $sourceDir;
    private $ocDir;
    private $agentService;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_agent_test_' . uniqid();
        $this->sourceDir = $this->testDir . '/agent_source';
        $this->ocDir = $this->testDir . '/opencart';

        mkdir($this->testDir);
        mkdir($this->sourceDir, 0755, true);
        mkdir($this->ocDir, 0755, true);

        // Создаем фиктивный репозиторий opencart_ai_agent
        file_put_contents($this->sourceDir . '/AGENTS.md', '# Agent Instructions');
        mkdir($this->sourceDir . '/.agents/skills/opencart3', 0755, true);
        file_put_contents($this->sourceDir . '/.agents/skills/opencart3/SKILL.md', "---\nname: opencart3\ndescription: OpenCart 3 Skill\n---\n# OpenCart 3");
        mkdir($this->sourceDir . '/.agents/rules', 0755, true);
        file_put_contents($this->sourceDir . '/.agents/rules/opencart.md', '# OC Rules');
        mkdir($this->sourceDir . '/.github', 0755, true);
        file_put_contents($this->sourceDir . '/.github/copilot-instructions.md', '# Copilot');

        // Создаем фиктивный OpenCart
        mkdir($this->ocDir . '/system', 0755, true);
        file_put_contents($this->ocDir . '/config.php', '<?php // opencart config');

        $fs = new FileSystemService($this->sourceDir, $this->ocDir);
        $this->agentService = new AgentSkillService($fs);
    }

    protected function tearDown(): void {
        $this->removeDir($this->testDir);
    }

    private function removeDir($dir) {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testResolveTargetPathExplicit() {
        $resolved = $this->agentService->resolveTargetPath($this->ocDir);
        $this->assertEquals(realpath($this->ocDir), realpath($resolved));
    }

    public function testInstallFromDirectoryCopiesFiles() {
        $result = $this->agentService->install($this->ocDir, [
            'source' => $this->sourceDir,
            'symlink' => false,
            'adapters' => 'cursor,claude',
        ]);

        $this->assertNotEmpty($result['files']);
        $this->assertFileExists($this->ocDir . '/AGENTS.md');
        $this->assertFileExists($this->ocDir . '/.agents/skills/opencart3/SKILL.md');
        $this->assertFileExists($this->ocDir . '/.agents/rules/opencart.md');
        $this->assertFileExists($this->ocDir . '/.github/copilot-instructions.md');
        $this->assertFileExists($this->ocDir . '/.cursorrules');
        $this->assertFileExists($this->ocDir . '/.cursor/rules/opencart.mdc');
        $this->assertFileExists($this->ocDir . '/.claude/skills/opencart3/SKILL.md');

        // Проверяем getInstalledInfo
        $info = $this->agentService->getInstalledInfo($this->ocDir);
        $this->assertTrue($info['has_agents_md']);
        $this->assertTrue($info['has_copilot_instructions']);
        $this->assertTrue($info['has_cursor_rules']);
        $this->assertCount(1, $info['skills']);
        $this->assertEquals('opencart3', $info['skills'][0]['name']);
    }

    public function testInstallWithSymlink() {
        $result = $this->agentService->install($this->ocDir, [
            'source' => $this->sourceDir,
            'symlink' => true,
            'adapters' => '',
        ]);

        $this->assertNotEmpty($result['files']);
        $this->assertTrue(is_link($this->ocDir . '/AGENTS.md'));
        $this->assertTrue(is_link($this->ocDir . '/.agents/skills/opencart3/SKILL.md'));
        $this->assertTrue(is_link($this->ocDir . '/.agents/rules/opencart.md'));
        $this->assertFileExists($this->ocDir . '/.agents/skills/opencart3/SKILL.md');
    }
}
