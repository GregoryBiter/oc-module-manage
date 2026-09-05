<?php

namespace Ocm\Commands;

use Ocm\Base\Command;
use Ocm\Base\Input;
use Ocm\Base\Output;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда удаления файлов модуля из OpenCart (module:remove / remove).
 */
class RemoveCommand extends Command {
    protected $name = 'module:remove';
    protected $description = 'Удаление файлов модуля и модификаций из OpenCart';

    protected function configure() {
        $this->setName('module:remove')
             ->setAliases(['remove'])
             ->setDescription('Удаление файлов модуля, OCMOD и записей из OpenCart')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Удалить без запроса подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $io->title('OCM: Удаление модуля из OpenCart');

        $config = $this->getService('config');
        $module = $this->getService('module');
        $openCart = $this->getService('opencart');
        $fileSystem = $this->getService('filesystem');

        $paths = $config->findOpenCartPaths();
        if (empty($paths)) {
            $io->error('Директория OpenCart не найдена!');
            return self::FAILURE;
        }

        if (!$input->getOption('force')) {
            if (!$io->confirm('Вы уверены, что хотите удалить модуль из OpenCart?', false)) {
                $io->note('Удаление отменено пользователем.');
                return self::SUCCESS;
            }
        }

        $files = $config->loadFilesList();

        foreach ($paths as $targetPath) {
            $io->section("Удаление из OpenCart: {$targetPath}");
            $identity = $module->resolveIdentity($targetPath);
            $moduleCode = $identity['code'] ?: basename($config->getCurrentDir());

            $removedCount = 0;
            foreach ($files as $relPath) {
                if ($fileSystem->removeFile($relPath)) {
                    $removedCount++;
                }
            }
            $io->text("  Удалено файлов: <info>{$removedCount}</info>");

            // Удаление из БД и OCMOD
            try {
                $db = $openCart->getOpenCartDbForPath($targetPath);
                if ($db) {
                    $query = $db->query("SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($moduleCode) . "'");
                    if ($query->num_rows) {
                        $db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($moduleCode) . "'");
                        $io->text("  OCMOD-модификатор удален из БД.");
                    }
                    $openCart->removeModuleFromDb($targetPath, $moduleCode);
                    $io->text("  Записи модуля очищены из таблиц ocm_*.");
                }
            } catch (\Throwable $e) {
                $io->warning("Предупреждение при очистке БД: " . $e->getMessage());
            }

            try {
                $openCart->refreshModifications($targetPath);
                $io->text("  Кэш модификаторов обновлен.");
            } catch (\Throwable $e) {
                $io->warning("Предупреждение при обновлении модификаторов: " . $e->getMessage());
            }
        }

        $io->success('Удаление модуля завершено!');
        return self::SUCCESS;
    }

    public function handle(Input $input, Output $output) {
        $config = $this->app->getService('config');
        $opencartPaths = defined('OPENCART_PATHS') ? OPENCART_PATHS : $config->findOpenCartPaths();

        foreach ($opencartPaths as $targetPath) {
            if (!is_dir($targetPath)) {
                $output->error("Директория OpenCart не существует: {$targetPath}");
                continue;
            }

            $output->info(">>> Удаление из: {$targetPath}");
            $this->removeFromPath($targetPath, $output);
        }

        $output->info("\nУдаление завершено.");
    }

    private function removeFromPath($targetPath, Output $output) {
        $config = $this->app->getService('config');
        $module = $this->app->getService('module');
        $openCart = $this->app->getService('opencart');
        $fileSystem = $this->app->getService('filesystem');

        $identity = $module->resolveIdentity($targetPath);
        $moduleCode = $identity['code'] ?: basename(getcwd());

        $files = $config->loadFilesList();
        if (empty($files)) {
            $output->comment("Нет записей о скопированных файлах в .ocm/files.json");
        } else {
            foreach ($files as $relativePath) {
                if ($fileSystem->removeFile($relativePath)) {
                    $output->writeln("  Удалено: {$relativePath}");
                }
            }
        }

        try {
            $db = $openCart->getOpenCartDbForPath($targetPath);
            if ($db) {
                $query = $db->query("SELECT * FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($moduleCode) . "'");
                if ($query->num_rows) {
                    $db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($moduleCode) . "'");
                    $output->info("  Удален OCMOD-модификатор из БД.");
                }

                $openCart->removeModuleFromDb($targetPath, $moduleCode);
                $output->info("  Удалены записи модуля из таблиц ocm_*.");
            }
            $openCart->refreshModifications($targetPath);
        } catch (\Throwable $e) {
            $output->warning("Предупреждение: " . $e->getMessage());
        }
    }
}
