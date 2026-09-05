<?php

namespace Ocm\Services;

/**
 * Сервис управления и запуска дополнительных внешних скриптов (ScriptService).
 * Поддерживает каскадный поиск:
 * 1. Локальные для проекта: ./.ocm/scripts/
 * 2. Пользовательские глобальные: ~/.config/ocm/scripts/
 * 3. Встроенные в пакет: scripts/ (например, lamp.sh)
 */
class ScriptService {
    protected $builtinScriptsDir;
    protected $fileSystem;

    public function __construct(FileSystemService $fileSystem = null, $builtinScriptsDir = null) {
        $this->fileSystem = $fileSystem ?: new FileSystemService();
        $this->builtinScriptsDir = $builtinScriptsDir ?: (defined('SCRIPT_DIR') ? SCRIPT_DIR . '/scripts' : dirname(dirname(__DIR__)) . '/scripts');
    }

    /**
     * Возвращает список директорий со скриптами в порядке приоритета:
     * 1. Локальные: ./.ocm/scripts
     * 2. Пользовательские: ~/.config/ocm/scripts
     * 3. Встроенные: scripts/
     */
    public function getScriptDirectories($currentDir = null) {
        $currentDir = $currentDir ?: (defined('CURRENT_DIR') ? CURRENT_DIR : getcwd());
        $dirs = [];

        // 1. Локальные (проект)
        $localDir = $currentDir . '/.ocm/scripts';
        if (is_dir($localDir)) {
            $dirs['local'] = $localDir;
        }

        // 2. Пользовательские (глобальные)
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if ($home) {
            $userDir = rtrim($home, '/\\') . '/.config/ocm/scripts';
            if (is_dir($userDir)) {
                $dirs['user'] = $userDir;
            }
        }

        // 3. Встроенные
        if (is_dir($this->builtinScriptsDir)) {
            $dirs['builtin'] = $this->builtinScriptsDir;
        }

        return $dirs;
    }

    /**
     * Получить список всех доступных скриптов.
     */
    public function getAvailableScripts($currentDir = null) {
        $dirs = $this->getScriptDirectories($currentDir);
        $scripts = [];

        // Проходим в обратном порядке (builtin -> user -> local), чтобы локальные перекрывали
        $searchOrder = array_reverse($dirs, true);
        foreach ($searchOrder as $type => $dir) {
            if (!is_dir($dir)) continue;
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || is_dir($dir . '/' . $item)) continue;
                $path = $dir . '/' . $item;
                $basename = pathinfo($item, PATHINFO_FILENAME);
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));

                $description = $this->extractDescription($path, $ext);

                $scripts[$basename] = [
                    'name' => $basename,
                    'filename' => $item,
                    'type' => $type,
                    'path' => $path,
                    'extension' => $ext,
                    'description' => $description
                ];
            }
        }

        return $scripts;
    }

    /**
     * Найти файл скрипта по имени или пути.
     */
    public function resolveScriptPath($nameOrPath, $currentDir = null) {
        // Если указан прямой путь
        if (file_exists($nameOrPath) && is_file($nameOrPath)) {
            return realpath($nameOrPath);
        }

        $dirs = $this->getScriptDirectories($currentDir);
        foreach ($dirs as $type => $dir) {
            $candidates = [
                $dir . '/' . $nameOrPath,
                $dir . '/' . $nameOrPath . '.sh',
                $dir . '/' . $nameOrPath . '.php'
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate) && is_file($candidate)) {
                    return realpath($candidate);
                }
            }
        }

        return null;
    }

    /**
     * Запустить скрипт с аргументами.
     */
    public function execute($scriptPath, array $args = []) {
        if (!file_exists($scriptPath) || !is_file($scriptPath)) {
            return 1;
        }

        $extension = strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION));
        $escapedArgs = array_map('escapeshellarg', $args);
        $argsStr = !empty($escapedArgs) ? ' ' . implode(' ', $escapedArgs) : '';

        if ($extension === 'php') {
            $cmd = 'php ' . escapeshellarg($scriptPath) . $argsStr;
        } elseif ($extension === 'sh') {
            $cmd = 'bash ' . escapeshellarg($scriptPath) . $argsStr;
        } else {
            if (!is_executable($scriptPath)) {
                @chmod($scriptPath, 0755);
            }
            $cmd = escapeshellarg($scriptPath) . $argsStr;
        }

        passthru($cmd, $exitCode);
        return $exitCode;
    }

    /**
     * Извлечь краткое описание из первых строк скрипта.
     */
    protected function extractDescription($path, $extension) {
        if ($pathinfo = pathinfo($path, PATHINFO_FILENAME)) {
            if ($pathinfo === 'lamp') {
                return 'Установка и развертывание LAMP-сервера (gb-lamp) для OpenCart';
            }
            if ($pathinfo === 'test') {
                return 'Тестовый скрипт для проверки работы';
            }
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return 'Пользовательский скрипт';

        foreach (array_slice($lines, 0, 5) as $line) {
            $line = trim($line);
            if (preg_match('/^(?:#|\/\/|\/\*)\s*(.+?)(?:\*\/)?$/', $line, $matches)) {
                $desc = trim($matches[1]);
                if ($desc && !preg_match('/^!/', $desc) && !preg_match('/^<\?php/', $desc)) {
                    return $desc;
                }
            }
        }

        return 'Пользовательский скрипт';
    }
}
