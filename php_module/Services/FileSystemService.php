<?php

namespace Ocm\Services;

/**
 * Сервис для управления файловой системой.
 */
class FileSystemService {
    protected $moduleDir;
    protected $opencartDir;

    public function __construct($moduleDir = null, $opencartDir = null) {
        $this->moduleDir = $moduleDir ?: (defined('MODULE_DIR') ? MODULE_DIR : '');
        $this->opencartDir = $opencartDir ?: (defined('OPENCART_DIR') ? OPENCART_DIR : '');
    }

    /**
     * Рекурсивный поиск всех файлов в директории.
     */
    public function findAllFiles($dir, $baseDir = null) {
        if ($baseDir === null) $baseDir = $dir;
        $files = [];
        if (!is_dir($dir)) return $files;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->findAllFiles($path, $baseDir));
            } else {
                $relative_path = substr($path, strlen($baseDir) + 1);
                $files[] = $relative_path;
            }
        }
        return $files;
    }

    /**
     * Рекурсивное копирование директории с заменой плейсхолдеров.
     */
    public function copyDirRecursively($src, $dst, array $placeholders = []) {
        if (!is_dir($src)) return;
        if (!is_dir($dst)) mkdir($dst, 0777, true);
        
        $items = scandir($src);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $srcPath = $src . '/' . $item;
            
            // Замена в имени файла/директории
            $newItem = $item;
            if (!empty($placeholders)) {
                $newItem = str_replace(array_keys($placeholders), array_values($placeholders), $item);
            }
            
            $dstPath = $dst . '/' . $newItem;
            
            if (is_dir($srcPath)) {
                $this->copyDirRecursively($srcPath, $dstPath, $placeholders);
            } else {
                $content = file_get_contents($srcPath);
                if (!empty($placeholders)) {
                    $content = str_replace(array_keys($placeholders), array_values($placeholders), $content);
                }
                file_put_contents($dstPath, $content);
            }
        }
    }

    /**
     * Синхронизация файла из модуля в OpenCart.
     */
    public function syncFile($relativePath, &$lastModifiedTimes = []) {
        $srcPath = $this->moduleDir . '/' . $relativePath;
        $destPath = $this->opencartDir . '/' . $relativePath;
        
        if (!file_exists($srcPath)) {
            return false;
        }

        // Проверка времени последней модификации
        $currentTime = filemtime($srcPath);
        if (isset($lastModifiedTimes[$relativePath]) && $lastModifiedTimes[$relativePath] == $currentTime) {
            return true;
        }
        
        $lastModifiedTimes[$relativePath] = $currentTime;
        
        // Создание директории для назначения
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        
        return copy($srcPath, $destPath);
    }

    /**
     * Удаление файла из OpenCart.
     */
    public function removeFile($relativePath) {
        $destPath = $this->opencartDir . '/' . $relativePath;
        $removed = false;

        if (file_exists($destPath)) {
            unlink($destPath);
            $removed = true;
        }
        
        // Удаление пустых папок вверх по дереву
        $parentDir = dirname($destPath);
        while ($parentDir != $this->opencartDir && is_dir($parentDir)) {
            $items = scandir($parentDir);
            if (count($items) <= 2) { // Только '.' и '..'
                rmdir($parentDir);
                $parentDir = dirname($parentDir);
            } else {
                break;
            }
        }

        return $removed;
    }

    /**
     * Поиск файлов по шаблону (glob-like)
     */
    public function findFilesByPattern($pattern, $currentDir = null) {
        $currentDir = $currentDir ?: (defined('CURRENT_DIR') ? CURRENT_DIR : getcwd());
        $files = [];
        
        // Заменяем ** на специальный маркер
        $regexPattern = str_replace('**', '{{ALL_SUBDIRS}}', $pattern);
        $regexPattern = str_replace('*', '{{ANY_FILES}}', $regexPattern);
        
        if (strpos($regexPattern, '{{ALL_SUBDIRS}}') !== false) {
            $parts = explode('{{ALL_SUBDIRS}}', $regexPattern);
            $baseSubDir = rtrim($parts[0], '/');
            $suffix = isset($parts[1]) ? ltrim($parts[1], '/') : '';
            
            $allFiles = $this->findAllFilesRelative($currentDir . '/' . $baseSubDir, $currentDir);
            
            foreach ($allFiles as $file) {
                if (empty($suffix) || (strpos($file, $suffix) !== false && strpos($file, $suffix) === (strlen($file) - strlen($suffix)))) {
                    $files[] = $file;
                }
            }
        } elseif (strpos($regexPattern, '{{ANY_FILES}}') !== false) {
            $dirGlob = str_replace(['{{ANY_FILES}}', '{{ALL_SUBDIRS}}'], ['*', '**'], $regexPattern);
            $matchedFiles = glob($currentDir . '/' . $dirGlob);
            
            foreach ($matchedFiles as $file) {
                if (is_file($file)) {
                    $files[] = substr($file, strlen($currentDir) + 1);
                }
            }
        } else {
            if (file_exists($currentDir . '/' . $pattern)) {
                $files[] = $pattern;
            }
        }
        
        return $files;
    }

    /**
     * Рекурсивный поиск всех файлов с относительными путями.
     */
    public function findAllFilesRelative($dir, $baseDir) {
        $files = [];
        if (!is_dir($dir)) return $files;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->findAllFilesRelative($path, $baseDir));
            } else {
                $files[] = substr($path, strlen($baseDir) + 1);
            }
        }
        return $files;
    }

    /**
     * Очистка директории.
     */
    public function cleanDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->cleanDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    /**
     * Проверить наличие расширения ZIP.
     */
    public function checkZipExtension() {
        return extension_loaded('zip');
    }

    /**
     * Рекурсивно добавляет директорию в ZIP-архив.
     */
    public function addDirToZip(\ZipArchive $zip, $dir, $zipDir) {
        if (!is_dir($dir)) return false;
        
        $files = scandir($dir);
        if ($files === false) return false;
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..' || $file == '.git') continue;

            $filePath = $dir . '/' . $file;
            $zipPath = $zipDir . ($zipDir ? '/' : '') . $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $filePath, $zipPath);
            } else if (is_file($filePath)) {
                $zip->addFile($filePath, $zipPath);
            }
        }
        
        return true;
    }

    /**
     * Разрешает шаблон с подстановочными символами * на реальные файлы.
     */
    public function resolveWildcardPattern($pattern, $baseDir) {
        $result = [];
        
        $regexPattern = str_replace(
            ['.', '*'], 
            ['\.', '(.*)'], 
            $pattern
        );
        $regexPattern = '#^' . $regexPattern . '$#';
        
        if (!is_dir($baseDir)) return $result;
        
        $allFiles = $this->findAllFilesRelative($baseDir, $baseDir);
        
        foreach ($allFiles as $file) {
            if (preg_match($regexPattern, $file)) {
                $result[] = $file;
            }
        }
        
        return $result;
    }
}
