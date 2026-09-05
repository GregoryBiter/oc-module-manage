<?php

namespace Ocm\Services;

class TemplateService {
    protected $builtinTemplatesDir;
    protected $fileSystem;

    public function __construct(FileSystemService $fileSystem = null, $builtinTemplatesDir = null) {
        $this->fileSystem = $fileSystem ?: new FileSystemService();
        $this->builtinTemplatesDir = $builtinTemplatesDir ?: (defined('TEMPLATES_DIR') ? TEMPLATES_DIR : dirname(dirname(__DIR__)) . '/templates');
    }

    /**
     * Возвращает список всех доступных директорий с шаблонами в порядке приоритета:
     * 1. Локальные (проект): ./.ocm/templates
     * 2. Пользовательские: ~/.config/ocm/templates
     * 3. Встроенные в пакет: templates/
     */
    public function getTemplateDirectories($currentDir = null) {
        $currentDir = $currentDir ?: (defined('CURRENT_DIR') ? CURRENT_DIR : getcwd());
        $dirs = [];

        // 1. Локальные
        $localDir = $currentDir . '/.ocm/templates';
        if (is_dir($localDir)) {
            $dirs['local'] = $localDir;
        }

        // 2. Пользовательские (глобальные)
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if ($home) {
            $userDir = rtrim($home, '/\\') . '/.config/ocm/templates';
            if (is_dir($userDir)) {
                $dirs['user'] = $userDir;
            }
        }

        // 3. Встроенные
        if (is_dir($this->builtinTemplatesDir)) {
            $dirs['builtin'] = $this->builtinTemplatesDir;
        }

        return $dirs;
    }

    /**
     * Возвращает список всех доступных шаблонов:
     * [ 'name' => 'my_module', 'type' => 'builtin|user|local', 'path' => '/path/to/template' ]
     */
    public function getAvailableTemplates($currentDir = null) {
        $dirs = $this->getTemplateDirectories($currentDir);
        $templates = [];

        // Проходим в обратном порядке (builtin -> user -> local), чтобы локальные перекрывали
        $searchOrder = array_reverse($dirs, true);
        foreach ($searchOrder as $type => $dir) {
            if (!is_dir($dir)) continue;
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . '/' . $item;
                if (is_dir($path)) {
                    $templates[$item] = [
                        'name' => $item,
                        'type' => $type,
                        'path' => $path
                    ];
                }
            }
        }

        return $templates;
    }

    /**
     * Получить путь к шаблону по имени.
     */
    public function getTemplatePath($name, $currentDir = null) {
        $templates = $this->getAvailableTemplates($currentDir);
        return isset($templates[$name]) ? $templates[$name]['path'] : null;
    }

    /**
     * Создать структуру модуля из шаблона.
     */
    public function createFromTemplate($templateName, $targetDir, array $placeholders, $currentDir = null) {
        $templatePath = $this->getTemplatePath($templateName, $currentDir);
        if (!$templatePath || !is_dir($templatePath)) {
            return false;
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $this->fileSystem->copyDirRecursively($templatePath, $targetDir, $placeholders);
        return true;
    }
}
