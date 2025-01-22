<?php
namespace OcModule;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;

use OcModule\JsonTool;
class ChangeHandler {
    private $opencart_dir;
    private $filesystem;
    private $json;
    private $last_modified_times = [];

    public function __construct($opencart_dir) {
        $this->opencart_dir = $opencart_dir;
        $this->json = new JsonTool();
        $this->filesystem = new Filesystem();
    }

    public function sync_file($src_path) {
        global $MODULE_DIR;
        $relative_path = str_replace($MODULE_DIR . '/', '', $src_path);
        $dest_path = $this->opencart_dir . '/' . $relative_path;

        try {
            $current_time = filemtime($src_path);
            if (isset($this->last_modified_times[$relative_path]) && $this->last_modified_times[$relative_path] == $current_time) {
                return;
            }

            $this->last_modified_times[$relative_path] = $current_time;

            $this->filesystem->mkdir(dirname($dest_path));
            $output = shell_exec("rsync -avz " . escapeshellarg($src_path) . " " . escapeshellarg($dest_path));
            echo $output;
            echo "Синхронизировано: $dest_path\n";

            $data = $this->json->load();
            $files = isset($data['files']) ? $data['files'] : [];
            if (!in_array($relative_path, $files)) {
                $files[] = $relative_path;
                $data['files'] = $files;
                $this->json->save($data);
            }
        } catch (IOExceptionInterface $exception) {
            echo "Пропущено (файл не найден): $src_path\n";
        }
    }

    public function remove_file($src_path) {
        global $MODULE_DIR;
        $relative_path = str_replace($MODULE_DIR . '/', '', $src_path);
        $dest_path = $this->opencart_dir . '/' . $relative_path;

        if (file_exists($dest_path)) {
            $this->filesystem->remove($dest_path);
            echo "Удалено: $dest_path\n";
        } else {
            echo "Пропущено (файл не найден): $dest_path\n";
        }

        $parent_dir = dirname($dest_path);
        while ($parent_dir != $this->opencart_dir) {
            if (is_dir($parent_dir) && count(scandir($parent_dir)) == 2) {
                $this->filesystem->remove($parent_dir);
                echo "Удалена пустая папка: " . str_replace($this->opencart_dir . '/', '', $parent_dir) . "\n";
            } else {
                break;
            }
            $parent_dir = dirname($parent_dir);
        }

        $data = $this->json->load();
        $files = isset($data['files']) ? $data['files'] : [];
        if (($key = array_search($relative_path, $files)) !== false) {
            unset($files[$key]);
            $data['files'] = $files;
            $this->json->save($data);
        }
    }
}