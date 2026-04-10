<?php

namespace Ocm\Services;

/**
 * Сервис для управления конфигурацией и метаданными модуля.
 */
class ConfigService {
    protected $currentDir;
    protected $jsonFile;
    protected $filesJson;

    public function __construct($currentDir = null) {
        $this->currentDir = $currentDir ?: (defined('CURRENT_DIR') ? CURRENT_DIR : getcwd());
        $this->jsonFile = $this->currentDir . '/opencart-module.json';
        $this->filesJson = $this->currentDir . '/.ocm_files.json';
    }

    public function loadJson($file) {
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    public function saveJson($file, $data) {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function loadFilesList() {
        $data = $this->loadJson($this->filesJson);
        return isset($data['files']) ? $data['files'] : [];
    }

    public function saveFilesList($files) {
        $this->saveJson($this->filesJson, ['files' => $files]);
    }

    public function loadModuleMetadata() {
        $data = $this->loadJson($this->jsonFile);
        if ($data) {
            unset($data['files']);
        }
        return $data;
    }

    public function saveModuleMetadata($metadata) {
        unset($metadata['files']);
        $this->saveJson($this->jsonFile, $metadata);
    }

    /**
     * Поиск путей к OpenCart.
     */
    public function findOpenCartPaths() {
        $paths = [];
        
        // .opencart
        $opencartFile = $this->currentDir . '/.opencart';
        if (file_exists($opencartFile)) {
            $lines = file($opencartFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $path = trim($line);
                if (!empty($path) && is_dir($path)) {
                    $paths[] = realpath($path);
                }
            }
        }

        // .path-opencart (legacy)
        if (empty($paths)) {
            $legacyFile = $this->currentDir . '/.path-opencart';
            if (file_exists($legacyFile)) {
                $path = trim(file_get_contents($legacyFile));
                if (!empty($path) && is_dir($path)) {
                    $paths[] = realpath($path);
                }
            }
        }

        // Search upwards for config.php
        if (empty($paths)) {
            $dir = $this->currentDir;
            while ($dir !== '/') {
                if (file_exists($dir . '/config.php') && file_exists($dir . '/admin/config.php')) {
                    $paths[] = realpath($dir);
                    break;
                }
                $dir = dirname($dir);
            }
        }

        return $paths;
    }

    public function parseInstallXmlMetadata() {
        $xmlFile = $this->currentDir . '/install.xml';
        if (!is_file($xmlFile)) return null;

        $xmlContent = file_get_contents($xmlFile);
        if (!$xmlContent) return null;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!@$dom->loadXML($xmlContent)) return null;

        $read = function($tag) use ($dom) {
            $node = $dom->getElementsByTagName($tag)->item(0);
            return $node ? trim($node->nodeValue) : '';
        };

        return [
            'xml' => $xmlContent,
            'code' => $read('code'),
            'name' => $read('name'),
            'version' => $read('version'),
            'author' => $read('author'),
            'link' => $read('link')
        ];
    }

    public function validateMetadata($metadata, &$errors = []) {
        if (!is_array($metadata)) {
            $errors[] = 'opencart-module.json должен содержать объект JSON';
            return false;
        }

        if (array_key_exists('files', $metadata)) {
            $errors[] = "Поле 'files' запрещено в opencart-module.json (используйте .ocm_files.json)";
        }

        return empty($errors);
    }

    public function inferCode($metadata) {
        if (!is_array($metadata)) return basename($this->currentDir);

        if (!empty($metadata['controller'])) {
            $controller = trim((string)$metadata['controller'], '/');
            $parts = explode('/', $controller);
            $type = count($parts) >= 3 ? $parts[count($parts) - 2] : 'module';
            $name = count($parts) >= 1 ? $parts[count($parts) - 1] : basename($this->currentDir);
            return preg_replace('/[^a-z0-9_]+/i', '_', strtolower($type . '_' . $name));
        }

        if (!empty($metadata['type']) && !empty($metadata['name'])) {
            return preg_replace('/[^a-z0-9_]+/i', '_', strtolower($metadata['type'] . '_' . $metadata['name']));
        }

        return basename($this->currentDir);
    }

    /**
     * Миграция данных из старого формата в новый.
     */
    public function migrateOldFormat() {
        if (!file_exists($this->jsonFile)) return false;
        
        $data = $this->loadJson($this->jsonFile);
        if (!$data || !isset($data['files'])) return false;
        
        $files = $data['files'];
        $this->saveFilesList($files);
        
        unset($data['files']);
        $this->saveModuleMetadata($data);
        
        return true;
    }

    /**
     * Проверяет соответствие строки шаблону с подстановочными символами *.
     */
    public function matchWildcardPattern($pattern, $string) {
        $regex = str_replace(['.', '*'], ['\.', '.*'], $pattern);
        return preg_match('#^' . $regex . '$#', $string) === 1;
    }

    /**
     * Преобразование snake_case в CamelCase.
     */
    public function toCamelCase($snakeStr) {
        $components = explode('_', $snakeStr);
        return implode('', array_map('ucfirst', $components));
    }

    /**
     * Преобразование snake_case в camelCase.
     */
    public function toCamelCaseLower($snakeStr) {
        $components = explode('_', $snakeStr);
        $first = array_shift($components);
        return $first . implode('', array_map('ucfirst', $components));
    }

    /**
     * Получить путь к директории модуля (upload).
     */
    public function getModuleDir() {
        return $this->currentDir . '/upload';
    }

    /**
     * Получить путь к основному JSON файлу.
     */
    public function getJsonFile() {
        return $this->jsonFile;
    }

    /**
     * Получить путь к файлу со списком файлов.
     */
    public function getFilesJson() {
        return $this->currentDir . '/.ocm_files.json';
    }
}
