<?php

namespace Ocm\Services;

/**
 * Основной сервис для управления жизненным циклом модуля.
 */
class ModuleService {
    protected $fileSystem;
    protected $config;
    protected $openCart;

    public function __construct(FileSystemService $fileSystem, ConfigService $config, OpenCartService $openCart) {
        $this->fileSystem = $fileSystem;
        $this->config = $config;
        $this->openCart = $openCart;
    }

    /**
     * Определение идентификации модуля.
     */
    public function resolveIdentity($targetPath) {
        $metadata = $this->config->loadModuleMetadata();
        $errors = [];
        $this->config->validateMetadata($metadata, $errors);

        $installXml = $this->config->parseInstallXmlMetadata();
        $db = $this->openCart->getOpenCartDbForPath($targetPath);

        $code = !empty($metadata['code']) ? $metadata['code'] : '';
        if ($code === '' && $installXml && !empty($installXml['code'])) {
            $code = $installXml['code'];
        }
        if ($code === '') {
            $code = $this->config->inferCode($metadata);
        }

        $name = '';
        if (!empty($metadata['module_name'])) {
            $name = $metadata['module_name'];
        } elseif (!empty($metadata['name'])) {
            $name = $metadata['name'];
        } elseif ($installXml && !empty($installXml['name'])) {
            $name = $installXml['name'];
        } else {
            $name = $code;
        }

        $version = '';
        if (!empty($metadata['version'])) {
            $version = $metadata['version'];
        } elseif ($installXml && !empty($installXml['version'])) {
            $version = $installXml['version'];
        } elseif ($db) {
            $version = $this->openCart->getExistingModificationVersion($db, $code);
        }
        if ($version === '') {
            $version = '0.0.0';
        }

        return [
            'code' => $code,
            'name' => $name,
            'version' => $version,
            'metadata' => is_array($metadata) ? $metadata : [],
            'install_xml' => $installXml,
            'errors' => $errors
        ];
    }

    /**
     * Синхронизация данных о модуле в БД OpenCart.
     */
    public function syncWithDb($targetPath, array $files) {
        $db = $this->openCart->getOpenCartDbForPath($targetPath);
        if (!$db) return;

        $this->openCart->ensureOcmTables($db);

        $identity = $this->resolveIdentity($targetPath);
        if (!empty($identity['errors'])) {
            return $identity['errors'];
        }

        $code = $identity['code'];
        $name = $identity['name'];
        $version = $identity['version'];
        $metadata = $identity['metadata'];
        $moduleType = !empty($metadata['type']) ? $metadata['type'] : 'module';

        $db->query("INSERT INTO `" . DB_PREFIX . "ocm_modules` SET
            `code` = '" . $db->escape($code) . "',
            `name` = '" . $db->escape($name) . "',
            `type` = '" . $db->escape($moduleType) . "',
            `installed_version` = '" . $db->escape($version) . "',
            `source` = 'ocm_cli',
            `metadata_json` = '" . $db->escape(json_encode($metadata, JSON_UNESCAPED_UNICODE)) . "',
            `status` = 1,
            `installed_at` = NOW(),
            `updated_at` = NOW()
            ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `type` = VALUES(`type`),
            `installed_version` = VALUES(`installed_version`),
            `metadata_json` = VALUES(`metadata_json`),
            `updated_at` = NOW()");

        $safeCode = $db->escape($code);
        $db->query("DELETE FROM `" . DB_PREFIX . "ocm_module_files` WHERE `module_code` = '" . $safeCode . "'");

        foreach ($files as $relativePath) {
            $targetFile = rtrim($targetPath, '/') . '/' . ltrim($relativePath, '/');
            $fileHash = is_file($targetFile) ? sha1_file($targetFile) : '';

            $db->query("INSERT INTO `" . DB_PREFIX . "ocm_module_files` SET
                `module_code` = '" . $safeCode . "',
                `file_path` = '" . $db->escape($relativePath) . "',
                `file_hash` = '" . $db->escape($fileHash) . "',
                `installed_at` = NOW(),
                `updated_at` = NOW()");
        }

        return true;
    }

    /**
     * Обработка install.xml.
     */
    public function handleOcmod($targetPath) {
        $identity = $this->resolveIdentity($targetPath);
        $installXml = $identity['install_xml'];
        
        if (!$installXml) return true;

        $db = $this->openCart->getOpenCartDbForPath($targetPath);
        if (!$db) return false;

        $code = $identity['code'];
        $name = $identity['name'];
        $version = $identity['version'];
        $author = isset($installXml['author']) ? $installXml['author'] : 'Unknown';
        $link = isset($installXml['link']) ? $installXml['link'] : '';

        $db->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE `code` = '" . $db->escape($code) . "'");
        $db->query("INSERT INTO `" . DB_PREFIX . "modification` SET 
            `code` = '" . $db->escape($code) . "',
            `name` = '" . $db->escape($name) . "',
            `author` = '" . $db->escape($author) . "',
            `version` = '" . $db->escape($version) . "',
            `link` = '" . $db->escape($link) . "',
            `xml` = '" . $db->escape($installXml['xml']) . "',
            `status` = 1,
            `date_added` = NOW()");

        $this->openCart->refreshModifications($targetPath);
        return true;
    }
}
