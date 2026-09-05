<?php

namespace Ocm\Services;

/**
 * Сервис для управления конфигурацией и метаданными модуля.
 */
class ConfigService {
    protected $currentDir;
    protected $jsonFile;
    protected $filesJson;
    protected $ocmDir;

    public function __construct($currentDir = null) {
        $this->currentDir = $currentDir ?: (defined('CURRENT_DIR') ? CURRENT_DIR : getcwd());
        $this->jsonFile = $this->currentDir . '/opencart-module.json';
        $this->ocmDir = $this->currentDir . '/.ocm';
        
        // Приоритет: .ocm/files.json, затем legacy .ocm_files.json
        if (file_exists($this->ocmDir . '/files.json')) {
            $this->filesJson = $this->ocmDir . '/files.json';
        } elseif (file_exists($this->currentDir . '/.ocm_files.json')) {
            $this->filesJson = $this->currentDir . '/.ocm_files.json';
        } else {
            $this->filesJson = $this->ocmDir . '/files.json';
        }
    }

    public function ensureOcmDir() {
        if (!is_dir($this->ocmDir)) {
            mkdir($this->ocmDir, 0777, true);
        }
    }

    public function loadJson($file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            return json_decode($content, true) ?: [];
        }
        return [];
    }

    public function saveJson($file, $data) {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function loadFilesList() {
        // Проверяем сначала новый путь .ocm/files.json
        if (file_exists($this->ocmDir . '/files.json')) {
            $data = $this->loadJson($this->ocmDir . '/files.json');
            if (isset($data['files'])) return $data['files'];
        }

        // Проверяем legacy .ocm_files.json
        $legacyFile = $this->currentDir . '/.ocm_files.json';
        if (file_exists($legacyFile)) {
            $data = $this->loadJson($legacyFile);
            return isset($data['files']) ? $data['files'] : [];
        }

        return [];
    }

    public function saveFilesList($files) {
        $this->ensureOcmDir();
        $this->saveJson($this->ocmDir . '/files.json', ['files' => $files]);
        // Также сохраняем legacy .ocm_files.json для обратной совместимости
        $this->saveJson($this->currentDir . '/.ocm_files.json', ['files' => $files]);
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
     * Сохранить целевой путь к OpenCart.
     */
    public function saveOpenCartTarget($path) {
        $this->ensureOcmDir();
        $realPath = realpath($path) ?: $path;
        file_put_contents($this->ocmDir . '/target', $realPath . "\n");
        // Дублируем в legacy .opencart
        file_put_contents($this->currentDir . '/.opencart', $realPath . "\n");
    }

    /**
     * Поиск путей к OpenCart.
     */
    public function findOpenCartPaths() {
        $paths = [];

        // 1. Переменная окружения OPENCART_DIR или OC_PATH
        $envPath = getenv('OPENCART_DIR') ?: getenv('OC_PATH');
        if ($envPath && is_dir($envPath)) {
            $paths[] = realpath($envPath);
        }

        // 2. .ocm/target (новый стандарт)
        $targetFile = $this->ocmDir . '/target';
        if (file_exists($targetFile)) {
            $lines = file($targetFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $path = trim($line);
                if (!empty($path) && is_dir($path)) {
                    $paths[] = realpath($path);
                }
            }
        }

        // 3. .opencart
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

        // 4. .path-opencart (legacy)
        $legacyFile = $this->currentDir . '/.path-opencart';
        if (file_exists($legacyFile)) {
            $path = trim(file_get_contents($legacyFile));
            if (!empty($path) && is_dir($path)) {
                $paths[] = realpath($path);
            }
        }

        // 5. Поиск вверх по дереву папок наличия config.php и admin/config.php
        if (empty($paths)) {
            $dir = $this->currentDir;
            while ($dir !== '/' && $dir !== '' && dirname($dir) !== $dir) {
                if (file_exists($dir . '/config.php') && file_exists($dir . '/admin/config.php')) {
                    $paths[] = realpath($dir);
                    break;
                }
                $dir = dirname($dir);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Получить путь к файлу модификатора (install.xml или index.xml).
     */
    public function getOcmodFilePath() {
        $candidates = [
            $this->currentDir . '/install.xml',
            $this->currentDir . '/index.xml',
            $this->currentDir . '/ocmod.xml',
        ];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Получить имя файла модификатора (install.xml или index.xml).
     */
    public function getOcmodFileName() {
        $path = $this->getOcmodFilePath();
        return $path ? basename($path) : null;
    }

    public function parseInstallXmlMetadata() {
        $xmlFile = $this->getOcmodFilePath();
        if (!$xmlFile || !is_file($xmlFile)) return null;

        $xmlContent = file_get_contents($xmlFile);
        if (!$xmlContent) return null;

        $fileName = basename($xmlFile);

        if (class_exists('\DOMDocument')) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            if (@$dom->loadXML($xmlContent)) {
                $read = function($tag) use ($dom) {
                    $node = $dom->getElementsByTagName($tag)->item(0);
                    return $node ? trim($node->nodeValue) : '';
                };

                return [
                    'file_name' => $fileName,
                    'file_path' => $xmlFile,
                    'xml' => $xmlContent,
                    'code' => $read('code'),
                    'name' => $read('name'),
                    'version' => $read('version'),
                    'author' => $read('author'),
                    'link' => $read('link')
                ];
            }
        }

        // Безопасный regex-фоллбэк для систем без ext-dom
        $extractTag = function($tag) use ($xmlContent) {
            if (preg_match('#<' . preg_quote($tag, '#') . '(?:\s+[^>]*)?>(.*?)</' . preg_quote($tag, '#') . '>#is', $xmlContent, $matches)) {
                return trim(strip_tags($matches[1]));
            }
            return '';
        };

        return [
            'file_name' => $fileName,
            'file_path' => $xmlFile,
            'xml' => $xmlContent,
            'code' => $extractTag('code'),
            'name' => $extractTag('name'),
            'version' => $extractTag('version'),
            'author' => $extractTag('author'),
            'link' => $extractTag('link')
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
        $migrated = false;

        // Миграция files из opencart-module.json
        if (file_exists($this->jsonFile)) {
            $data = $this->loadJson($this->jsonFile);
            if ($data && isset($data['files'])) {
                $files = $data['files'];
                $this->saveFilesList($files);
                unset($data['files']);
                $this->saveModuleMetadata($data);
                $migrated = true;
            }
        }

        // Миграция .ocm_files.json в .ocm/files.json
        $legacyFiles = $this->currentDir . '/.ocm_files.json';
        $newFiles = $this->ocmDir . '/files.json';
        if (file_exists($legacyFiles) && !file_exists($newFiles)) {
            $this->ensureOcmDir();
            copy($legacyFiles, $newFiles);
            $migrated = true;
        }

        // Миграция .opencart в .ocm/target
        $legacyOpenCart = $this->currentDir . '/.opencart';
        $newTarget = $this->ocmDir . '/target';
        if (file_exists($legacyOpenCart) && !file_exists($newTarget)) {
            $this->ensureOcmDir();
            copy($legacyOpenCart, $newTarget);
            $migrated = true;
        }

        return $migrated;
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

    public function getModuleDir() {
        return $this->currentDir . '/upload';
    }

    public function getJsonFile() {
        return $this->jsonFile;
    }

    public function getFilesJson() {
        return $this->filesJson;
    }

    public function getCurrentDir() {
        return $this->currentDir;
    }

    public function getOcmDir() {
        return $this->ocmDir;
    }
}
