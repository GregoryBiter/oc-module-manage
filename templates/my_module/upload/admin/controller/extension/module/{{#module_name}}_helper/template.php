<?php
trait GBMyModuleTemplates {

    
    private $templates = [
        [
            'path' => 'template/common/header.twig',
            'insert_code' => "{{ microdata }}",
            'target' => '</head>',
            'type' => 'before'
        ],
        [
            'path' => 'template/common/footer.twig',
            'insert_code' => "<!-- Footer modification -->",
            'target' => '</footer>',
            'type' => 'after'
        ]
    ];

    //** Install template */
    public function installTemplate() {
        $backup_data = [];

        foreach ($this->templates as $template) {
            $template_path = $template['path'];
            $original_template = $this->getOriginalTemplate($template_path);

            if ($original_template) {
                $backup_data[$template_path] = $original_template;
                $modified_template = $this->modifyTemplate(
                    $original_template,
                    $template['insert_code'],
                    $template['target'],
                    $template['type']
                );

                if ($modified_template !== $original_template) {
                    $this->saveModifiedTemplate($template_path, $modified_template);
                }
            }
        }

        if (!empty($backup_data)) {
            $this->saveBackupData($backup_data);
        }
    }

    private function getOriginalTemplate($theme_path) {
        $this->load->model('design/theme');
        $template = $this->model_design_theme->getTheme(
            $this->config->get('config_store_id'),
            $this->config->get('config_theme'),
            $theme_path
        );

        if (!$template) {
            return $this->loadTemplateFromFileSystem($theme_path);
        }

        return $template['code'];
    }

    private function loadTemplateFromFileSystem($theme_path) {
        $theme_directory = DIR_CATALOG . 'view/theme/' . $this->config->get('config_theme') . '/' . $theme_path;

        if (file_exists($theme_directory)) {
            return file_get_contents($theme_directory);
        }

        return null;
    }

    private function modifyTemplate($template, $insert, $target, $type = 'after') {
        if (strpos($template, $insert) !== false) {
            return $template;
        }

        switch ($type) {
            case 'after':
                return $this->insertAfter($template, $insert, $target);
            case 'before':
                return $this->insertBefore($template, $insert, $target);
            case 'replace':
                return str_replace($target, $insert, $template);
            default:
                return $template;
        }
    }

    private function insertAfter($template, $insert, $target) {
        $position = strpos($template, $target);
        if ($position !== false) {
            return substr_replace($template, $target . "\n" . $insert, $position, strlen($target));
        }
        return $template;
    }

    private function insertBefore($template, $insert, $target) {
        $position = strpos($template, $target);
        if ($position !== false) {
            return substr_replace($template, $insert . "\n" . $target, $position, strlen($target));
        }
        return $template;
    }

    private function saveModifiedTemplate($template_path, $modified_template) {
        $this->model_design_theme->editTheme(
            $this->config->get('config_store_id'),
            $this->config->get('config_theme'),
            $template_path,
            $modified_template
        );
    }

    private function saveBackupData($backup_data) {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting($this->module_name . '_backup', $backup_data);
    }
}