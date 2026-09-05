<?php

namespace Ocm\Services;

class DatabaseService {
    protected $connections = [];

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

        // Попытка 1: Запуск изолированного подпроцесса PHP для гарантированного считывания
        $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $subScript = sprintf(
            'error_reporting(0); require %s; echo json_encode(get_defined_constants(true)["user"] ?? []);',
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
        $hostname = $constants['DB_HOSTNAME'] ?? null;
        $username = $constants['DB_USERNAME'] ?? null;
        $password = $constants['DB_PASSWORD'] ?? '';
        $database = $constants['DB_DATABASE'] ?? null;
        $port = !empty($constants['DB_PORT']) ? (int)$constants['DB_PORT'] : 3306;
        $prefix = $constants['DB_PREFIX'] ?? '';

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
            'target_path' => $realPath
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
        $pdo = $this->getPdo($targetPath);
        $trimmedSql = trim($sql);
        $isSelect = preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $trimmedSql);

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

    /**
     * Получить общую информацию о базе данных.
     */
    public function getInfo($targetPath) {
        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            return null;
        }

        $pdo = $this->getPdo($targetPath);

        $version = $pdo->query("SELECT VERSION() as v")->fetch()['v'] ?? 'Unknown';

        // Получение размера базы данных и количества таблиц
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

    /**
     * Получить список таблиц с префиксом или фильтром.
     */
    public function getTables($targetPath, $filterPrefix = null) {
        $pdo = $this->getPdo($targetPath);
        $creds = $this->getCredentials($targetPath);

        $sql = "SELECT table_name, table_rows, round((data_length + index_length) / 1024, 2) as size_kb 
                FROM information_schema.tables 
                WHERE table_schema = :db";

        if ($filterPrefix) {
            $sql .= " AND table_name LIKE :prefix";
        }
        $sql .= " ORDER BY table_name ASC";

        $stmt = $pdo->prepare($sql);
        $params = ['db' => $creds['database']];
        if ($filterPrefix) {
            $params['prefix'] = $filterPrefix . '%';
        }
        $stmt->execute($params);

        return $stmt->fetchAll();
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

        // Вариант А: использование системной утилиты mysqldump, если она доступна
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

        // Вариант Б: Встроенный экспорт через PDO (чистый PHP)
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

        // Вариант А: через системную утилиту mysql
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

        // Вариант Б: через PDO
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
     * Запустить интерактивную консоль mysql.
     */
    public function launchTerminal($targetPath) {
        if (!$this->hasCommand('mysql')) {
            throw new \RuntimeException("Системный клиент 'mysql' не найден в PATH. Установите mysql-client.");
        }

        $creds = $this->getCredentials($targetPath);
        if (!$creds) {
            throw new \RuntimeException("Не удалось получить реквизиты БД");
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

        passthru($cmd, $exitCode);
        return $exitCode;
    }

    protected function hasCommand($cmd) {
        $check = shell_exec("command -v {$cmd} 2>/dev/null");
        return !empty(trim((string)$check));
    }
}
