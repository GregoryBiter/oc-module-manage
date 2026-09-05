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

    protected $aliases = [
        'my_module' => 'crud',
        'ocm_gbt_extension_module' => 'standard',
        'basic' => 'standard',
        'default' => 'standard',
        'advanced' => 'crud',
        'modifier' => 'ocmod'
    ];

    /**
     * Возвращает список всех доступных шаблонов:
     * [ 'name' => 'standard', 'type' => 'builtin|user|local', 'path' => '/path/to/template' ]
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

        // Приоритетный порядок для стандартных шаблонов (standard первый)
        $preferredOrder = ['standard', 'crud', 'ocmod'];
        $sortedTemplates = [];
        foreach ($preferredOrder as $pref) {
            if (isset($templates[$pref])) {
                $sortedTemplates[$pref] = $templates[$pref];
                unset($templates[$pref]);
            }
        }
        foreach ($templates as $key => $val) {
            $sortedTemplates[$key] = $val;
        }

        return $sortedTemplates;
    }

    /**
     * Получить путь к шаблону по имени (с поддержкой алиасов).
     */
    public function getTemplatePath($name, $currentDir = null) {
        $templates = $this->getAvailableTemplates($currentDir);
        if (isset($templates[$name])) {
            return $templates[$name]['path'];
        }

        // Проверка алиасов
        if (isset($this->aliases[$name]) && isset($templates[$this->aliases[$name]])) {
            return $templates[$this->aliases[$name]]['path'];
        }

        return null;
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
