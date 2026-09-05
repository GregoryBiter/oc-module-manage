<?php

namespace Ocm\Services;

/**
 * Сервис управления AI Agent скилами и правилами (opencart_ai_agent).
 */
class AgentSkillService {
    const DEFAULT_REPO_HTTPS = 'https://github.com/GregoryBiter/opencart_ai_agent.git';
    const DEFAULT_REPO_SSH = 'git@github.com:GregoryBiter/opencart_ai_agent.git';
    const DEFAULT_REPO = self::DEFAULT_REPO_HTTPS;
    const DEFAULT_BRANCH = 'main';

    protected $fileSystem;

    public function __construct(?FileSystemService $fileSystem = null) {
        $this->fileSystem = $fileSystem ?: new FileSystemService();
    }

    /**
     * Определение целевой директории OpenCart для установки скилов.
     */
    public function resolveTargetPath($explicitPath = null, ?ConfigService $configService = null) {
        if (!empty($explicitPath)) {
            $real = realpath($explicitPath);
            if ($real && is_dir($real)) {
                return $real;
            }
            return $explicitPath;
        }

        $cwd = getcwd();

        // 1. Проверяем, не является ли текущая папка корнем OpenCart
        if ($this->isOpenCartRoot($cwd)) {
            return $cwd;
        }

        // 2. Проверяем, есть ли подпапка www (например, в LAMP-окружении)
        if (is_dir($cwd . '/www') && $this->isOpenCartRoot($cwd . '/www')) {
            return realpath($cwd . '/www');
        }

        // 3. Проверяем привязанный OpenCart через .ocm/target
        if ($configService) {
            $paths = $configService->findOpenCartPaths();
            if (!empty($paths) && is_dir($paths[0])) {
                return $paths[0];
            }
        } else {
            $cfg = new ConfigService($cwd);
            $paths = $cfg->findOpenCartPaths();
            if (!empty($paths) && is_dir($paths[0])) {
                return $paths[0];
            }
        }

        return null;
    }

    /**
     * Проверка, является ли директория корнем OpenCart.
     */
    public function isOpenCartRoot($path) {
        if (!$path || !is_dir($path)) return false;
        return file_exists($path . '/index.php') && (file_exists($path . '/admin/config.php') || is_dir($path . '/admin'));
    }

    /**
     * Установка скилов и инструкций.
     */
    public function install($targetPath, array $options = []) {
        $cleanUpTemp = null;
        $sourceDir = null;

        // 1. Источник файлов: локальный каталог (--source) или клонирование из Git репозитория
        if (!empty($options['source']) && is_dir($options['source'])) {
            $sourceDir = realpath($options['source']);
        } else {
            $sourceDir = $this->fetchRepository($options, $cleanUpTemp);
        }

        if (!$sourceDir || !is_dir($sourceDir)) {
            throw new \RuntimeException("Не удалось получить репозиторий со скилами.");
        }

        $installed = [
            'skills' => [],
            'rules' => [],
            'files' => [],
            'adapters' => [],
            'target' => $targetPath
        ];

        try {
            // Режим глобальной установки
            if (!empty($options['global'])) {
                $installedGlobal = $this->installGlobal($sourceDir, $options);
                $installed['global'] = $installedGlobal;
                return $installed;
            }

            if (!$targetPath || !is_dir($targetPath)) {
                throw new \RuntimeException("Целевая директория OpenCart не найдена: {$targetPath}");
            }

            $symlink = !empty($options['symlink']) && !empty($options['source']);

            if ($sourceDir) {
                // 2. Копирование / симлинк AGENTS.md из репозитория
                if (file_exists($sourceDir . '/AGENTS.md')) {
                    $destAgents = $targetPath . '/AGENTS.md';
                    $this->deployFile($sourceDir . '/AGENTS.md', $destAgents, $symlink);
                    $installed['files'][] = 'AGENTS.md';
                }

                // 3. Копирование / симлинк .agents/ (skills и rules)
                if (is_dir($sourceDir . '/.agents')) {
                    $destAgentsDir = $targetPath . '/.agents';
                    $this->deployDirectory($sourceDir . '/.agents', $destAgentsDir, $symlink);

                    // Сбор списка установленных скилов
                    if (is_dir($destAgentsDir . '/skills')) {
                        $skills = scandir($destAgentsDir . '/skills');
                        foreach ($skills as $s) {
                            if ($s !== '.' && $s !== '..' && is_dir($destAgentsDir . '/skills/' . $s)) {
                                $installed['skills'][] = $s;
                            }
                        }
                    }

                    // Сбор списка правил
                    if (is_dir($destAgentsDir . '/rules')) {
                        $rules = scandir($destAgentsDir . '/rules');
                        foreach ($rules as $r) {
                            if ($r !== '.' && $r !== '..' && is_file($destAgentsDir . '/rules/' . $r)) {
                                $installed['rules'][] = $r;
                            }
                        }
                    }
                }

                // 4. Копирование .github/ (инструкции для GitHub Copilot)
                if (is_dir($sourceDir . '/.github')) {
                    $destGithubDir = $targetPath . '/.github';
                    $this->deployDirectory($sourceDir . '/.github', $destGithubDir, $symlink);
                    $installed['files'][] = '.github/copilot-instructions.md';
                }

                // 5. Копирование dev-modules/ (CONTRIBUTING.md и инструкции для модулей)
                if (is_dir($sourceDir . '/dev-modules')) {
                    $destDevModulesDir = $targetPath . '/dev-modules';
                    if (!is_dir($destDevModulesDir)) {
                        mkdir($destDevModulesDir, 0777, true);
                    }
                    if (file_exists($sourceDir . '/dev-modules/CONTRIBUTING.md')) {
                        $this->deployFile($sourceDir . '/dev-modules/CONTRIBUTING.md', $destDevModulesDir . '/CONTRIBUTING.md', $symlink);
                        $installed['files'][] = 'dev-modules/CONTRIBUTING.md';
                    }
                    if (is_dir($sourceDir . '/dev-modules/opencart-instructions')) {
                        $this->deployDirectory($sourceDir . '/dev-modules/opencart-instructions', $destDevModulesDir . '/opencart-instructions', $symlink);
                        $installed['files'][] = 'dev-modules/opencart-instructions';
                    }
                }
            }

            // 6. Адаптеры для других AI инструментов (Cursor, Claude Code)
            $adapters = $options['adapters'] ?? 'default';
            if ($adapters === 'all' || strpos($adapters, 'cursor') !== false) {
                $this->configureCursorAdapter($targetPath, $symlink);
                $installed['adapters'][] = 'Cursor (.cursorrules & .cursor/rules/)';
            }
            if ($adapters === 'all' || strpos($adapters, 'claude') !== false) {
                $this->configureClaudeAdapter($targetPath, $symlink);
                $installed['adapters'][] = 'Claude Code (.claude/skills/)';
            }

        } finally {
            if ($cleanUpTemp && is_dir($cleanUpTemp)) {
                $this->removeDirectory($cleanUpTemp);
            }
        }

        return $installed;
    }

    /**
     * Глобальная установка скилов в домашнюю директорию пользователя.
     */
    protected function installGlobal($sourceDir, array $options) {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');
        if (!$home) {
            throw new \RuntimeException("Не удалось определить домашнюю директорию пользователя.");
        }

        $targets = [
            $home . '/.agents/skills',
            $home . '/.config/ocm/skills'
        ];

        // Проверяем наличие Antigravity CLI skills
        if (is_dir($home . '/.gemini/antigravity-cli')) {
            $targets[] = $home . '/.gemini/antigravity-cli/skills';
        }
        if (is_dir($home . '/.gemini')) {
            $targets[] = $home . '/.gemini/config/skills';
        }

        $symlink = !empty($options['symlink']) && !empty($options['source']);
        $installed = [];

        // Скилы из репозитория
        if ($sourceDir && is_dir($sourceDir . '/.agents/skills')) {
            foreach ($targets as $targetDir) {
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $skills = scandir($sourceDir . '/.agents/skills');
                foreach ($skills as $skill) {
                    if ($skill === '.' || $skill === '..') continue;
                    $src = $sourceDir . '/.agents/skills/' . $skill;
                    $dst = $targetDir . '/' . $skill;
                    if (is_dir($src)) {
                        $this->deployDirectory($src, $dst, $symlink);
                        $installed[$targetDir][] = $skill;
                    }
                }
            }
        }

        return $installed;
    }

    /**
     * Загрузка репозитория через Git или HTTP-архив.
     */
    protected function fetchRepository(array $options, &$tempDir) {
        $tempDir = sys_get_temp_dir() . '/ocm_ai_agent_' . uniqid();
        $repo = $options['repo'] ?? self::DEFAULT_REPO;
        $branch = $options['branch'] ?? self::DEFAULT_BRANCH;

        // Попытка 1: git clone с указанным репозиторием (по умолчанию HTTPS)
        $cmd = sprintf(
            'git clone --depth=1 --branch %s %s %s 2>&1',
            escapeshellarg($branch),
            escapeshellarg($repo),
            escapeshellarg($tempDir)
        );

        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        if ($exitCode === 0 && is_dir($tempDir . '/.agents')) {
            return $tempDir;
        }

        // Попытка 2: если репозиторий HTTPS и не удался, пробуем SSH fallback (или наоборот)
        $fallbackRepo = null;
        if ($repo === self::DEFAULT_REPO_HTTPS) {
            $fallbackRepo = self::DEFAULT_REPO_SSH;
        } elseif ($repo === self::DEFAULT_REPO_SSH) {
            $fallbackRepo = self::DEFAULT_REPO_HTTPS;
        }

        if ($fallbackRepo) {
            $this->removeDirectory($tempDir);
            $cmd = sprintf(
                'git clone --depth=1 --branch %s %s %s 2>&1',
                escapeshellarg($branch),
                escapeshellarg($fallbackRepo),
                escapeshellarg($tempDir)
            );
            $output = [];
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && is_dir($tempDir . '/.agents')) {
                return $tempDir;
            }
        }

        // Попытка 3: скачивание архива через curl/file_get_contents
        $this->removeDirectory($tempDir);
        mkdir($tempDir, 0777, true);
        $zipUrl = "https://github.com/GregoryBiter/opencart_ai_agent/archive/refs/heads/{$branch}.zip";
        $zipFile = $tempDir . '/repo.zip';

        if ($this->downloadFile($zipUrl, $zipFile) && class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
                @unlink($zipFile);

                $extractedDir = $tempDir . "/opencart_ai_agent-{$branch}";
                if (is_dir($extractedDir)) {
                    return $extractedDir;
                }
            }
        }

        throw new \RuntimeException("Не удалось скачать репозиторий со скилами:\n" . implode("\n", $output));
    }

    /**
     * Настройка адаптера для Cursor IDE.
     */
    protected function configureCursorAdapter($targetPath, $symlink = false) {
        $rulesDir = $targetPath . '/.agents/rules';
        $ruleFile = null;
        if (file_exists($rulesDir . '/opencart.md')) {
            $ruleFile = $rulesDir . '/opencart.md';
        } elseif (file_exists($rulesDir . '/ocm.md')) {
            $ruleFile = $rulesDir . '/ocm.md';
        } elseif (file_exists($targetPath . '/AGENTS.md')) {
            $ruleFile = $targetPath . '/AGENTS.md';
        }

        if (!$ruleFile) {
            return;
        }

        // 1. Корень .cursorrules
        $this->deployFile($ruleFile, $targetPath . '/.cursorrules', $symlink);

        // 2. Папка .cursor/rules/
        $cursorRulesDir = $targetPath . '/.cursor/rules';
        if (!is_dir($cursorRulesDir)) {
            mkdir($cursorRulesDir, 0777, true);
        }
        $this->deployFile($ruleFile, $cursorRulesDir . '/opencart.mdc', $symlink);
    }

    /**
     * Настройка адаптера для Claude Code.
     */
    protected function configureClaudeAdapter($targetPath, $symlink = false) {
        $skillsSrc = $targetPath . '/.agents/skills';
        if (!is_dir($skillsSrc)) {
            return;
        }

        $claudeSkillsDir = $targetPath . '/.claude/skills';
        $this->deployDirectory($skillsSrc, $claudeSkillsDir, $symlink);
    }

    /**
     * Копирование или создание символической ссылки на файл.
     */
    protected function deployFile($src, $dst, $symlink = false) {
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (is_link($dst) || file_exists($dst)) {
            @unlink($dst);
        }

        if ($symlink) {
            symlink($src, $dst);
        } else {
            copy($src, $dst);
        }
    }

    /**
     * Копирование или создание символической ссылки на директорию.
     */
    protected function deployDirectory($src, $dst, $symlink = false) {
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }

        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }

            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                $this->deployDirectory($srcPath, $dstPath, $symlink);
            } else {
                $this->deployFile($srcPath, $dstPath, $symlink);
            }
        }
    }

    /**
     * Получить список установленных скилов и правил в OpenCart.
     */
    public function getInstalledInfo($targetPath) {
        $info = [
            'has_agents_md' => file_exists($targetPath . '/AGENTS.md'),
            'has_copilot_instructions' => file_exists($targetPath . '/.github/copilot-instructions.md'),
            'has_cursor_rules' => file_exists($targetPath . '/.cursorrules') || is_dir($targetPath . '/.cursor/rules'),
            'skills' => [],
            'rules' => [],
        ];

        $skillsDir = $targetPath . '/.agents/skills';
        if (is_dir($skillsDir)) {
            $items = scandir($skillsDir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $skillDir = $skillsDir . '/' . $item;
                if (is_dir($skillDir)) {
                    $skillMd = $skillDir . '/SKILL.md';
                    $description = 'OpenCart AI Skill';
                    if (file_exists($skillMd)) {
                        $content = file_get_contents($skillMd);
                        if (preg_match('/description:\s*["\']?(.*?)["\']?(\r?\n|$)/', $content, $m)) {
                            $description = trim($m[1]);
                        }
                    }
                    $info['skills'][] = [
                        'name' => $item,
                        'description' => $description,
                        'path' => $skillDir
                    ];
                }
            }
        }

        $rulesDir = $targetPath . '/.agents/rules';
        if (is_dir($rulesDir)) {
            $items = scandir($rulesDir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $ruleFile = $rulesDir . '/' . $item;
                if (is_file($ruleFile)) {
                    $info['rules'][] = [
                        'file' => $item,
                        'path' => $ruleFile
                    ];
                }
            }
        }

        return $info;
    }

    protected function downloadFile($url, $destination) {
        $fp = @fopen($destination, 'w+');
        if (!$fp) return false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'OCM-CLI/2.0');
        $success = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $success && filesize($destination) > 0;
    }

    protected function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
