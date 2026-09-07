<?php

namespace Ocm\Services;

class DatabaseService {
    protected $connections = [];

    /**
     * Поиск файла docker-compose.yml для целевой папки OpenCart.
     */
    public function findDockerComposeFile($targetPath) {
        $realPath = realpath($targetPath);
        if (!$realPath) {
            return null;
        }

        $candidates = [
            $realPath . '/docker-compose.yml',
            $realPath . '/docker-compose.yaml',
            dirname($realPath) . '/docker-compose.yml',
            dirname($realPath) . '/docker-compose.yaml',
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Проверка, запущен ли сервис в Docker Compose.
     */
    public function isDockerServiceRunning($composeFile, $serviceName) {
        if (!$composeFile || !file_exists($composeFile)) {
            return false;
        }

        $cmd = sprintf('docker compose -f %s ps -q %s 2>/dev/null', escapeshellarg($composeFile), escapeshellarg($serviceName));
        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        return $exitCode === 0 && !empty($output) && trim(implode('', $output)) !== '';
    }

    /**
     * Загрузка и парсинг .env с подстановкой переменных (${VAR}).
     */
    public function loadEnvVars($targetPath) {
        $realPath = realpath($targetPath);
        if (!$realPath) {
            return [];
        }

        $envFiles = [
            dirname($realPath) . '/.env',
            $realPath . '/.env'
        ];

        $vars = [];
        foreach ($envFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) continue;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                $vars[$k] = $v;
            }
        }

        // Рекурсивное раскрытие ${VAR}
        $changed = true;
        $maxPasses = 5;
        while ($changed && $maxPasses-- > 0) {
            $changed = false;
            foreach ($vars as $k => $v) {
                $expanded = preg_replace_callback('/\$\{([a-zA-Z0-9_]+)\}/', function($m) use ($vars) {
                    return $vars[$m[1]] ?? (getenv($m[1]) ?: '');
                }, $v);
                if ($expanded !== $v) {
                    $vars[$k] = $expanded;
                    $changed = true;
                }
            }
        }

        return $vars;
    }

    /**
     * Извлечь реквизиты подключения к БД из config.php OpenCart.
     */
    public function getCredentials($targetPath) {
        $realPath = realpath($targetPath);
        if (!$realPath) {
            return null;
        }

        $configFile = $realPath . '/config.php';
        if (!file_exists($configFile)) {
            return null;
        }

        $envVars = $this->loadEnvVars($realPath);
        $composeFile = $this->findDockerComposeFile($realPath);

        // Попытка 1: Запуск изолированного подпроцесса PHP с пробросом переменных .env
        $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $envCode = '';
        foreach ($envVars as $k => $v) {
            $envCode .= sprintf('$_ENV[%s] = %s; putenv(%s); ', var_export($k, true), var_export($v, true), var_export($k . '=' . $v, true));
        }

        $subScript = sprintf(
            'error_reporting(0); %s require %s; echo json_encode(get_defined_constants(true)["user"] ?? []);',
            $envCode,
            var_export($configFile, true)
        );

        $cmd = escapeshellarg($phpBinary) . ' -r ' . escapeshellarg($subScript);
        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        $constants = [];
        if ($exitCode === 0 && !empty($output)) {
            $constants = json_decode(implode('', $output), true) ?: [];
        }

        // Если подпроцесс не дал результат, используем Regex-фоллбэк
        if (empty($constants)) {
            $content = file_get_contents($configFile);
            $extract = function($name) use ($content) {
                if (preg_match("/define\\(['\"]" . preg_quote($name, '/') . "['\"]\\s*,\\s*['\"](.*?)['\"]\\)/", $content, $m)) {
                    return $m[1];
                }
                return null;
            };

            $constants = [
                'DB_DRIVER' => $extract('DB_DRIVER'),
                'DB_HOSTNAME' => $extract('DB_HOSTNAME'),
                'DB_USERNAME' => $extract('DB_USERNAME'),
                'DB_PASSWORD' => $extract('DB_PASSWORD'),
                'DB_DATABASE' => $extract('DB_DATABASE'),
                'DB_PORT' => $extract('DB_PORT'),
                'DB_PREFIX' => $extract('DB_PREFIX'),
            ];
        }

        $driver = $constants['DB_DRIVER'] ?? 'mysqli';
        $hostname = $constants['DB_HOSTNAME'] ?? ($envVars['OC_DB_HOST'] ?? ($envVars['MYSQL_HOST'] ?? null));
        $username = $constants['DB_USERNAME'] ?? ($envVars['OC_DB_USER'] ?? ($envVars['MYSQL_USER'] ?? null));
        $password = $constants['DB_PASSWORD'] ?? ($envVars['OC_DB_PASSWORD'] ?? ($envVars['MYSQL_PASSWORD'] ?? ''));
        $database = $constants['DB_DATABASE'] ?? ($envVars['OC_DB_NAME'] ?? ($envVars['MYSQL_DATABASE'] ?? null));
        $port = !empty($constants['DB_PORT']) ? (int)$constants['DB_PORT'] : (!empty($envVars['MYSQL_PORT']) ? (int)$envVars['MYSQL_PORT'] : 3306);
        $prefix = $constants['DB_PREFIX'] ?? ($envVars['OC_DB_PREFIX'] ?? '');

        // Если запущено на хосте (не внутри Docker), а хост указан как 'db' или имя контейнера
        $isHost = !file_exists('/.dockerenv');
        if ($isHost && ($hostname === 'db' || ($hostname && gethostbyname($hostname) === $hostname && $hostname !== '127.0.0.1' && $hostname !== 'localhost'))) {
            $hostname = '127.0.0.1';
            if (!empty($envVars['MYSQL_PORT'])) {
                $port = (int)$envVars['MYSQL_PORT'];
            }
        }

        if (!$hostname || !$username || !$database) {
            return null;
        }

        return [
            'driver' => $driver,
            'hostname' => $hostname,
            'username' => $username,
            'password' => (string)$password,
            'database' => $database,
            'port' => $port,
            'prefix' => (string)$prefix,
            'target_path' => $realPath,
            'docker_compose' => $composeFile,
            'env' => $envVars
        ];
    }

    /**
     * Получить PDO-подключение к базе данных OpenCart.
     */
    public function getPdo($targetPath) {
        $realPath = realpath($targetPath);
        if (isset($this->connections[$realPath])) {
            return $this->connections[$realPath];
        }

        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            throw new \RuntimeException("Не удалось прочитать параметры подключения к БД из {$targetPath}/config.php");
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $creds['hostname'],
            $creds['port'],
            $creds['database']
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new \PDO($dsn, $creds['username'], $creds['password'], $options);
            $this->connections[$realPath] = $pdo;
            return $pdo;
        } catch (\PDOException $e) {
            throw new \RuntimeException("Ошибка подключения к БД ({$creds['hostname']}:{$creds['database']}): " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Выполнить произвольный SQL-запрос.
     */
    public function query($targetPath, $sql) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            throw new \RuntimeException("Не удалось получить параметры подключения к БД для {$targetPath}");
        }

        $trimmedSql = trim($sql);
        $isSelect = preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $trimmedSql);

        if (extension_loaded('pdo_mysql')) {
            $pdo = $this->getPdo($targetPath);
            if ($isSelect) {
                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll();
                $columns = [];
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                }
                return [
                    'type' => 'select',
                    'columns' => $columns,
                    'rows' => $rows,
                    'count' => count($rows)
                ];
            }

            $affected = $pdo->exec($sql);
            return [
                'type' => 'exec',
                'affected' => $affected
            ];
        }

        // Docker bridge: если на хосте нет pdo_mysql, но запущен контейнер MariaDB/MySQL
        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            return $this->queryViaDocker($creds, $sql, $isSelect);
        }

        throw new \RuntimeException("Расширение PHP pdo_mysql не установлено на хосте, а контейнер базы данных Docker не запущен.");
    }

    /**
     * Выполнение SQL-запроса через Docker Compose exec к сервису db.
     */
    public function queryViaDocker(array $creds, $sql, $isSelect = true) {
        $cmd = sprintf(
            'docker compose -f %s exec -T db mariadb -u %s -p%s %s --batch --raw -e %s 2>&1',
            escapeshellarg($creds['docker_compose']),
            escapeshellarg($creds['username']),
            escapeshellarg($creds['password']),
            escapeshellarg($creds['database']),
            escapeshellarg($sql)
        );

        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Ошибка выполнения SQL через Docker: " . implode("\n", $output));
        }

        if (!$isSelect) {
            return [
                'type' => 'exec',
                'affected' => 1
            ];
        }

        $columns = [];
        $rows = [];
        if (!empty($output)) {
            $columns = explode("\t", array_shift($output));
            foreach ($output as $line) {
                if ($line === '') continue;
                $vals = explode("\t", $line);
                $row = [];
                foreach ($columns as $idx => $col) {
                    $row[$col] = $vals[$idx] ?? null;
                }
                $rows[] = $row;
            }
        }

        return [
            'type' => 'select',
            'columns' => $columns,
            'rows' => $rows,
            'count' => count($rows)
        ];
    }

    /**
     * Получить общую информацию о базе данных.
     */
    public function getInfo($targetPath) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            return null;
        }

        if (extension_loaded('pdo_mysql')) {
            $pdo = $this->getPdo($targetPath);
            $version = $pdo->query("SELECT VERSION() as v")->fetch()['v'] ?? 'Unknown';

            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as table_count,
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = :db
            ");
            $stmt->execute(['db' => $creds['database']]);
            $stats = $stmt->fetch() ?: ['table_count' => 0, 'size_mb' => 0];

            return [
                'database' => $creds['database'],
                'hostname' => $creds['hostname'],
                'port' => $creds['port'],
                'username' => $creds['username'],
                'prefix' => $creds['prefix'],
                'server_version' => $version,
                'table_count' => (int)$stats['table_count'],
                'size_mb' => (float)$stats['size_mb'],
            ];
        }

        // Docker fallback
        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $vRes = $this->queryViaDocker($creds, "SELECT VERSION() as v", true);
            $version = $vRes['rows'][0]['v'] ?? 'Unknown';

            $sql = sprintf(
                "SELECT COUNT(*) as table_count, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = '%s'",
                addslashes($creds['database'])
            );
            $sRes = $this->queryViaDocker($creds, $sql, true);
            $stats = $sRes['rows'][0] ?? ['table_count' => 0, 'size_mb' => 0];

            return [
                'database' => $creds['database'],
                'hostname' => $creds['hostname'],
                'port' => $creds['port'],
                'username' => $creds['username'],
                'prefix' => $creds['prefix'],
                'server_version' => $version,
                'table_count' => (int)($stats['table_count'] ?? 0),
                'size_mb' => (float)($stats['size_mb'] ?? 0),
            ];
        }

        return null;
    }

    /**
     * Получить список таблиц с префиксом или фильтром.
     */
    public function getTables($targetPath, $filterPrefix = null) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            return [];
        }

        $sql = "SELECT table_name, table_rows, round((data_length + index_length) / 1024, 2) as size_kb 
                FROM information_schema.tables 
                WHERE table_schema = '" . addslashes($creds['database']) . "'";

        if ($filterPrefix) {
            $sql .= " AND table_name LIKE '" . addslashes($filterPrefix) . "%'";
        }
        $sql .= " ORDER BY table_name ASC";

        if (extension_loaded('pdo_mysql')) {
            $pdo = $this->getPdo($targetPath);
            return $pdo->query($sql)->fetchAll();
        }

        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $res = $this->queryViaDocker($creds, $sql, true);
            return $res['rows'];
        }

        return [];
    }

    /**
     * Экспорт базы данных (дамп).
     */
    public function dump($targetPath, $outputFile, array $options = []) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            throw new \RuntimeException("Не удалось получить реквизиты БД");
        }

        $tables = $options['tables'] ?? [];
        $isGzip = !empty($options['gzip']) || substr($outputFile, -3) === '.gz';

        // Вариант 1: Docker Compose mariadb-dump (если проект в Docker и контейнер db запущен)
        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $tableArgs = '';
            if (!empty($tables)) {
                foreach ($tables as $tbl) {
                    $tableArgs .= ' ' . escapeshellarg($tbl);
                }
            }

            $cmd = sprintf(
                'docker compose -f %s exec -T db mariadb-dump -u %s -p%s --single-transaction --quick --add-drop-table %s%s',
                escapeshellarg($creds['docker_compose']),
                escapeshellarg($creds['username']),
                escapeshellarg($creds['password']),
                escapeshellarg($creds['database']),
                $tableArgs
            );

            if ($isGzip) {
                $cmd .= ' | gzip';
            }
            $cmd .= ' > ' . escapeshellarg($outputFile);

            $exitCode = 1;
            system($cmd, $exitCode);
            return $exitCode === 0;
        }

        // Вариант 2: использование системной утилиты mysqldump, если она доступна
        if ($this->hasCommand('mysqldump')) {
            $cmd = sprintf(
                'mysqldump -h %s -P %d -u %s',
                escapeshellarg($creds['hostname']),
                $creds['port'],
                escapeshellarg($creds['username'])
            );

            if ($creds['password'] !== '') {
                $cmd .= ' -p' . escapeshellarg($creds['password']);
            }

            $cmd .= ' --single-transaction --quick --add-drop-table';
            $cmd .= ' ' . escapeshellarg($creds['database']);

            if (!empty($tables)) {
                foreach ($tables as $tbl) {
                    $cmd .= ' ' . escapeshellarg($tbl);
                }
            }

            if ($isGzip) {
                $cmd .= ' | gzip';
            }

            $cmd .= ' > ' . escapeshellarg($outputFile);

            $exitCode = 1;
            system($cmd, $exitCode);
            return $exitCode === 0;
        }

        // Вариант 3: Встроенный экспорт через PDO (чистый PHP)
        return $this->dumpViaPdo($targetPath, $outputFile, $tables, $isGzip);
    }

    /**
     * Дамп через PDO (fallback без необходимости mysqldump).
     */
    protected function dumpViaPdo($targetPath, $outputFile, array $tables = [], $isGzip = false) {
        $pdo = $this->getPdo($targetPath);
        $creds = $this->getCredentials($targetPath);

        if (empty($tables)) {
            $tableRows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(\PDO::FETCH_NUM);
            $tables = array_map(function($row) { return $row[0]; }, $tableRows);
        }

        $fp = $isGzip ? gzopen($outputFile, 'w9') : fopen($outputFile, 'w');
        if (!$fp) {
            throw new \RuntimeException("Не удалось открыть файл для записи: {$outputFile}");
        }

        $write = function($text) use ($fp, $isGzip) {
            if ($isGzip) {
                gzwrite($fp, $text);
            } else {
                fwrite($fp, $text);
            }
        };

        $write("-- OCM Database Dump\n");
        $write("-- Database: `{$creds['database']}`\n");
        $write("-- Generated: " . date('Y-m-d H:i:s') . "\n\n");
        $write("SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        foreach ($tables as $table) {
            $write("-- Table structure for `{$table}`\n");
            $write("DROP TABLE IF EXISTS `{$table}`;\n");

            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
            $write($createRow[1] . ";\n\n");

            // Данные
            $write("-- Dumping data for `{$table}`\n");
            $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
            $rowsChunk = [];

            while ($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
                $escapedValues = array_map(function($val) use ($pdo) {
                    if ($val === null) return 'NULL';
                    return $pdo->quote($val);
                }, array_values($row));

                $rowsChunk[] = '(' . implode(', ', $escapedValues) . ')';

                if (count($rowsChunk) >= 100) {
                    $cols = '`' . implode('`, `', array_keys($row)) . '`';
                    $write("INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $rowsChunk) . ";\n");
                    $rowsChunk = [];
                }
            }

            if (!empty($rowsChunk)) {
                $cols = '`' . implode('`, `', array_keys($row)) . '`';
                $write("INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $rowsChunk) . ";\n");
            }
            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");

        if ($isGzip) {
            gzclose($fp);
        } else {
            fclose($fp);
        }

        return true;
    }

    /**
     * Импорт SQL-файла в базу данных OpenCart.
     */
    public function import($targetPath, $inputFile) {
        if (!file_exists($inputFile)) {
            throw new \RuntimeException("Файл для импорта не найден: {$inputFile}");
        }

        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            throw new \RuntimeException("Не удалось получить реквизиты БД");
        }

        $isGzip = substr($inputFile, -3) === '.gz';

        // Вариант 1: Docker Compose import (если контейнер db запущен)
        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $cmd = sprintf(
                '%s %s | docker compose -f %s exec -T db mariadb -u %s -p%s %s',
                $isGzip ? 'gunzip -c' : 'cat',
                escapeshellarg($inputFile),
                escapeshellarg($creds['docker_compose']),
                escapeshellarg($creds['username']),
                escapeshellarg($creds['password']),
                escapeshellarg($creds['database'])
            );

            $exitCode = 1;
            system($cmd, $exitCode);
            return $exitCode === 0;
        }

        // Вариант 2: через системную утилиту mysql
        if ($this->hasCommand('mysql')) {
            $cmd = sprintf(
                '%s %s | mysql -h %s -P %d -u %s',
                $isGzip ? 'gunzip -c' : 'cat',
                escapeshellarg($inputFile),
                escapeshellarg($creds['hostname']),
                $creds['port'],
                escapeshellarg($creds['username'])
            );

            if ($creds['password'] !== '') {
                $cmd .= ' -p' . escapeshellarg($creds['password']);
            }

            $cmd .= ' ' . escapeshellarg($creds['database']);

            $exitCode = 1;
            system($cmd, $exitCode);
            return $exitCode === 0;
        }

        // Вариант 3: через PDO
        $pdo = $this->getPdo($targetPath);
        $fp = $isGzip ? gzopen($inputFile, 'r') : fopen($inputFile, 'r');
        if (!$fp) {
            throw new \RuntimeException("Не удалось открыть файл: {$inputFile}");
        }

        $currentQuery = '';
        while (($line = ($isGzip ? gzgets($fp) : fgets($fp))) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
                continue;
            }

            $currentQuery .= $line;
            if (substr($trimmed, -1) === ';') {
                $pdo->exec($currentQuery);
                $currentQuery = '';
            }
        }

        if ($isGzip) gzclose($fp);
        else fclose($fp);

        return true;
    }

    /**
     * Синхронизация OCMOD модификатора в базу данных.
     */
    public function syncModificationToDb($targetPath, $code, $name, $author, $version, $link, $xmlContent) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            return false;
        }

        $prefix = $creds['prefix'];

        if (extension_loaded('pdo_mysql')) {
            $pdo = $this->getPdo($targetPath);
            $delStmt = $pdo->prepare("DELETE FROM `{$prefix}modification` WHERE `code` = :code");
            $delStmt->execute([':code' => $code]);

            $insertStmt = $pdo->prepare("
                INSERT INTO `{$prefix}modification` 
                (`code`, `name`, `author`, `version`, `link`, `xml`, `status`, `date_added`) 
                VALUES 
                (:code, :name, :author, :version, :link, :xml, 1, NOW())
            ");
            return $insertStmt->execute([
                ':code' => $code,
                ':name' => $name,
                ':author' => $author,
                ':version' => $version,
                ':link' => $link,
                ':xml' => $xmlContent
            ]);
        }

        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $hexXml = '0x' . bin2hex($xmlContent);
            $sql = sprintf(
                "DELETE FROM `%smodification` WHERE `code` = '%s'; INSERT INTO `%smodification` (`code`, `name`, `author`, `version`, `link`, `xml`, `status`, `date_added`) VALUES ('%s', '%s', '%s', '%s', '%s', %s, 1, NOW());",
                $prefix,
                addslashes($code),
                $prefix,
                addslashes($code),
                addslashes($name),
                addslashes($author),
                addslashes($version),
                addslashes($link),
                $hexXml
            );
            $this->queryViaDocker($creds, $sql, false);
            return true;
        }

        return false;
    }

    /**
     * Удаление OCMOD модификатора из базы данных.
     */
    public function removeModificationFromDb($targetPath, $code) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            return false;
        }

        $prefix = $creds['prefix'];

        if (extension_loaded('pdo_mysql')) {
            $pdo = $this->getPdo($targetPath);
            $delStmt = $pdo->prepare("DELETE FROM `{$prefix}modification` WHERE `code` = :code");
            return $delStmt->execute([':code' => $code]);
        }

        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $sql = sprintf("DELETE FROM `%smodification` WHERE `code` = '%s';", $prefix, addslashes($code));
            $this->queryViaDocker($creds, $sql, false);
            return true;
        }

        return false;
    }

    /**
     * Запустить интерактивную консоль mysql.
     */
    public function launchTerminal($targetPath) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            throw new \RuntimeException("Не удалось получить реквизиты БД");
        }

        // Вариант 1: через Docker Compose
        if (!empty($creds['docker_compose']) && $this->isDockerServiceRunning($creds['docker_compose'], 'db')) {
            $cmd = sprintf(
                'docker compose -f %s exec db mariadb -u %s -p%s %s',
                escapeshellarg($creds['docker_compose']),
                escapeshellarg($creds['username']),
                escapeshellarg($creds['password']),
                escapeshellarg($creds['database'])
            );
            return $this->runInteractiveCommand($cmd);
        }

        if (!$this->hasCommand('mysql')) {
            throw new \RuntimeException("Системный клиент 'mysql' не найден в PATH, и контейнер Docker db не запущен. Установите mysql-client.");
        }

        $cmd = sprintf(
            'mysql -h %s -P %d -u %s',
            escapeshellarg($creds['hostname']),
            $creds['port'],
            escapeshellarg($creds['username'])
        );

        if ($creds['password'] !== '') {
            $cmd .= ' -p' . escapeshellarg($creds['password']);
        }

        $cmd .= ' ' . escapeshellarg($creds['database']);

        return $this->runInteractiveCommand($cmd);
    }

    /**
     * Запустить интерактивную команду, сохранив доступ дочернего процесса к TTY.
     *
     * passthru() не всегда корректно передаёт стандартный ввод docker compose exec,
     * из-за чего клиент MariaDB открывается без приглашения и не принимает команды.
     */
    protected function runInteractiveCommand($cmd) {
        $pipes = [];
        $process = proc_open($cmd, [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ], $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Не удалось запустить интерактивный клиент базы данных.');
        }

        return proc_close($process);
    }

    protected function hasCommand($cmd) {
        $check = shell_exec("command -v {$cmd} 2>/dev/null");
        return !empty(trim((string)$check));
    }
}
