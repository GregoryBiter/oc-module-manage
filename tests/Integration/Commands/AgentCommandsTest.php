<?php

namespace Tests\Integration\Commands;

use Ocm\Base\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class AgentCommandsTest extends TestCase {
    private $testDir;
    private $srcDir;
    private $ocDir;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ocm_cmd_agent_test_' . uniqid();
        $this->srcDir = $this->testDir . '/agent_source';
        $this->ocDir = $this->testDir . '/opencart';

        mkdir($this->testDir);
        mkdir($this->srcDir, 0755, true);
        mkdir($this->ocDir, 0755, true);

        // Фиктивный исходный репозиторий
        file_put_contents($this->srcDir . '/AGENTS.md', '# Agent Rules');
        mkdir($this->srcDir . '/.agents/skills/opencart3', 0755, true);
        file_put_contents($this->srcDir . '/.agents/skills/opencart3/SKILL.md', "---\nname: opencart3\ndescription: OpenCart 3 Skill\n---\n");
        mkdir($this->srcDir . '/.agents/rules', 0755, true);
        file_put_contents($this->srcDir . '/.agents/rules/opencart.md', '# OC Rules');
        mkdir($this->srcDir . '/.github', 0755, true);
        file_put_contents($this->srcDir . '/.github/copilot-instructions.md', '# Copilot');

        // Фиктивный OpenCart
        mkdir($this->ocDir . '/system', 0755, true);
        file_put_contents($this->ocDir . '/config.php', '<?php');
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

    public function testAgentInstallAndListCommands() {
        $app = new Application();
        $app->setAutoExit(false);

        // 1. Выполняем agent:install
        $installInput = new ArrayInput([
            'command' => 'agent:install',
            'path' => $this->ocDir,
            '--source' => $this->srcDir,
            '--force' => true,
        ]);
        $output = new BufferedOutput();
        $code = $app->run($installInput, $output);

        $this->assertEquals(0, $code);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('opencart3', $outputContent);
        $this->assertFileExists($this->ocDir . '/AGENTS.md');
        $this->assertFileExists($this->ocDir . '/.agents/skills/opencart3/SKILL.md');

        // 2. Выполняем agent:list
        $listInput = new ArrayInput([
            'command' => 'agent:list',
            'path' => $this->ocDir,
        ]);
        $listOutput = new BufferedOutput();
        $listCode = $app->run($listInput, $listOutput);

        $this->assertEquals(0, $listCode);
        $listContent = $listOutput->fetch();
        $this->assertStringContainsString('opencart3', $listContent);
        $this->assertStringContainsString('OpenCart 3 Skill', $listContent);
        $this->assertStringContainsString('AGENTS.md', $listContent);
    }
}
