<?php
require_once(DIR_SYSTEM . 'library/probg_team/slug.php');

class ModelExtensionModuleProbgTeam extends Model {
    const VERSION = '1.0.1';
    const SCHEMA_VERSION = '1.0.0';

    private $schema_tables = array(
        'team_category',
        'team_category_description',
        'team_category_to_store',
        'team_category_to_layout',
        'team_member',
        'team_member_description',
        'team_member_image',
        'team_member_to_store',
        'team_setting_description'
    );

    public function install() {
        $this->upgrade(true);
    }

    public function upgrade($force = false) {
        if (!$force && $this->getSchemaVersion() === self::SCHEMA_VERSION) {
            // Version/default settings may change without a database schema change.
            $this->ensureDefaultSettings();
            return;
        }

        $this->createSchema();
        $this->ensureWorkingHoursColumn();
        $this->ensureDefaultSettings();
        $this->migrateStoreAssignments();
        $this->synchronizeMemberStores();
        $this->migrateAndCleanupLegacyStructuredSettings();
        $this->setSchemaVersion(self::SCHEMA_VERSION);
    }

    private function createSchema() {
        $queries = array(
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_category` (
                `team_category_id` INT(11) NOT NULL AUTO_INCREMENT,
                `sort_order` INT(11) NOT NULL DEFAULT '0',
                `status` TINYINT(1) NOT NULL DEFAULT '1',
                `date_added` DATETIME NOT NULL,
                `date_modified` DATETIME NOT NULL,
                PRIMARY KEY (`team_category_id`),
                KEY `status_sort` (`status`, `sort_order`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_category_description` (
                `team_category_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` MEDIUMTEXT NOT NULL,
                `meta_title` VARCHAR(255) NOT NULL,
                `meta_description` VARCHAR(255) NOT NULL,
                `meta_keyword` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`team_category_id`, `language_id`),
                KEY `name` (`name`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_member` (
                `team_member_id` INT(11) NOT NULL AUTO_INCREMENT,
                `team_category_id` INT(11) NOT NULL,
                `image` VARCHAR(255) NOT NULL,
                `sort_order` INT(11) NOT NULL DEFAULT '0',
                `status` TINYINT(1) NOT NULL DEFAULT '1',
                `date_added` DATETIME NOT NULL,
                `date_modified` DATETIME NOT NULL,
                PRIMARY KEY (`team_member_id`),
                KEY `category_status_sort` (`team_category_id`, `status`, `sort_order`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_member_description` (
                `team_member_id` INT(11) NOT NULL,
                `language_id` INT(11) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `short_description` MEDIUMTEXT NOT NULL,
                `description` MEDIUMTEXT NOT NULL,
                `telephone` VARCHAR(64) NOT NULL,
                `city` VARCHAR(255) NOT NULL,
                `working_hours` MEDIUMTEXT NOT NULL,
                `website` VARCHAR(255) NOT NULL,
                `facebook` VARCHAR(255) NOT NULL,
                `instagram` VARCHAR(255) NOT NULL,
                `youtube` VARCHAR(255) NOT NULL,
                `linkedin` VARCHAR(255) NOT NULL,
                `meta_title` VARCHAR(255) NOT NULL,
                `meta_description` VARCHAR(255) NOT NULL,
                `meta_keyword` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`team_member_id`, `language_id`),
                KEY `name` (`name`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_member_image` (
                `team_member_image_id` INT(11) NOT NULL AUTO_INCREMENT,
                `team_member_id` INT(11) NOT NULL,
                `image` VARCHAR(255) NOT NULL,
                `sort_order` INT(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`team_member_image_id`),
                KEY `member_sort` (`team_member_id`, `sort_order`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_setting_description` (
                `store_id` INT(11) NOT NULL DEFAULT '0',
                `language_id` INT(11) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` MEDIUMTEXT NOT NULL,
                `meta_title` VARCHAR(255) NOT NULL,
                `meta_description` VARCHAR(255) NOT NULL,
                `meta_keyword` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`store_id`, `language_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_category_to_store` (
                `team_category_id` INT(11) NOT NULL,
                `store_id` INT(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`team_category_id`, `store_id`),
                KEY `store_id` (`store_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_category_to_layout` (
                `team_category_id` INT(11) NOT NULL,
                `store_id` INT(11) NOT NULL DEFAULT '0',
                `layout_id` INT(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`team_category_id`, `store_id`),
                KEY `layout_id` (`layout_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci",
            "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "team_member_to_store` (
                `team_member_id` INT(11) NOT NULL,
                `store_id` INT(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`team_member_id`, `store_id`),
                KEY `store_id` (`store_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        foreach ($queries as $sql) {
            $this->db->query($sql);
        }
    }

    private function ensureWorkingHoursColumn() {
        $column = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "team_member_description` WHERE Field = 'working_hours'");

        if (!$column->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "team_member_description` ADD `working_hours` MEDIUMTEXT NULL AFTER `city`");
            $this->db->query("UPDATE `" . DB_PREFIX . "team_member_description` SET working_hours = '' WHERE working_hours IS NULL");
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "team_member_description` MODIFY `working_hours` MEDIUMTEXT NOT NULL");
        } elseif (isset($column->row['Null']) && strtoupper($column->row['Null']) === 'YES') {
            $this->db->query("UPDATE `" . DB_PREFIX . "team_member_description` SET working_hours = '' WHERE working_hours IS NULL");
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "team_member_description` MODIFY `working_hours` MEDIUMTEXT NOT NULL");
        }
    }

    private function ensureDefaultSettings() {
        $defaults = array(
            'module_probg_team_version' => self::VERSION,
            'module_probg_team_instances_migrated' => '0',
            'module_probg_team_show_working_hours' => '1',
            'module_probg_team_open_graph_status' => '1',
            'module_probg_team_schema_status' => '1',
            'module_probg_team_cache_status' => '1',
            'module_probg_team_search_status' => '1',
            'module_probg_team_search_limit' => '6',
            'module_probg_team_sitemap_status' => '1'
        );

        foreach ($defaults as $key => $value) {
            $setting = $this->db->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

            if (!$setting->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', code = 'module_probg_team', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', serialized = '0'");
            }
        }

        $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape(self::VERSION) . "', serialized = '0' WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = 'module_probg_team_version'");
    }

    private function migrateStoreAssignments() {
        $store_subquery = "SELECT 0 AS store_id UNION ALL SELECT store_id FROM `" . DB_PREFIX . "store`";

        // Repair only records that do not have any store assignment. This preserves deliberate visibility choices.
        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "team_category_to_store` (`team_category_id`, `store_id`)
            SELECT c.team_category_id, stores.store_id
            FROM `" . DB_PREFIX . "team_category` c
            CROSS JOIN (" . $store_subquery . ") stores
            WHERE NOT EXISTS (
                SELECT 1 FROM `" . DB_PREFIX . "team_category_to_store` c2s WHERE c2s.team_category_id = c.team_category_id
            )");

        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "team_member_to_store` (`team_member_id`, `store_id`)
            SELECT m.team_member_id, c2s.store_id
            FROM `" . DB_PREFIX . "team_member` m
            INNER JOIN `" . DB_PREFIX . "team_category_to_store` c2s ON (m.team_category_id = c2s.team_category_id)
            WHERE NOT EXISTS (
                SELECT 1 FROM `" . DB_PREFIX . "team_member_to_store` m2s WHERE m2s.team_member_id = m.team_member_id
            )");
    }

    private function synchronizeMemberStores() {
        $this->db->query("DELETE m2s FROM `" . DB_PREFIX . "team_member_to_store` m2s
            INNER JOIN `" . DB_PREFIX . "team_member` m ON (m2s.team_member_id = m.team_member_id)
            LEFT JOIN `" . DB_PREFIX . "team_category_to_store` c2s ON (m.team_category_id = c2s.team_category_id AND m2s.store_id = c2s.store_id)
            WHERE c2s.team_category_id IS NULL");
    }

    private function migrateAndCleanupLegacyStructuredSettings() {
        // v0.5.x-v0.7.x also stored these arrays in the standard setting table.
        // Recover them first when a normalized table was lost or is empty, then remove the duplicate rows.
        $legacy_descriptions = $this->getLegacySettingArray('module_probg_team_description');
        $description_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_setting_description`")->row['total'];

        if (!$description_total && $legacy_descriptions) {
            $this->saveDescriptions($legacy_descriptions);
            $description_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_setting_description`")->row['total'];
        }

        if ($description_total || !$legacy_descriptions) {
            $this->deleteLegacySetting('module_probg_team_description');
        }

        $legacy_seo_urls = $this->getLegacySettingArray('module_probg_team_seo_url');
        $seo_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1'")->row['total'];

        if (!$seo_total && $legacy_seo_urls) {
            $this->saveSeoUrls($legacy_seo_urls, $this->getDescriptions());
            $seo_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1'")->row['total'];
        }

        if ($seo_total || !$legacy_seo_urls) {
            $this->deleteLegacySetting('module_probg_team_seo_url');
        }
    }

    private function getLegacySettingArray($key) {
        $query = $this->db->query("SELECT `value`, serialized FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");

        if (!$query->num_rows) {
            return array();
        }

        $value = $query->row['value'];

        if (!empty($query->row['serialized'])) {
            $decoded = json_decode($value, true);

            if (!is_array($decoded)) {
                $decoded = @unserialize($value);
            }

            return is_array($decoded) ? $decoded : array();
        }

        return is_array($value) ? $value : array();
    }

    private function deleteLegacySetting($key) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = '" . $this->db->escape($key) . "'");
    }

    public function uninstall() {
        $queries = array(
            "DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1' OR `query` LIKE 'probg_team_category_id=%' OR `query` LIKE 'probg_team_member_id=%'",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_member_image`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_member_description`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_member_to_store`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_member`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_category_description`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_category_to_layout`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_category_to_store`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_category`",
            "DROP TABLE IF EXISTS `" . DB_PREFIX . "team_setting_description`"
        );

        foreach ($queries as $sql) {
            $this->db->query($sql);
        }

        $this->rotateCacheVersion();
    }

    public function prepareData($data) {
        $valid_stores = $this->getValidStoreIds();
        $valid_languages = $this->getValidLanguageIds();
        $descriptions = isset($data['module_probg_team_description']) ? $data['module_probg_team_description'] : array();

        if ($descriptions && $this->isLanguageIndexedDescriptionArray($descriptions)) {
            $descriptions = array(0 => $descriptions);
        }

        $descriptions = $this->filterStoreLanguageData($descriptions, $valid_stores, $valid_languages);

        foreach ($descriptions as $store_id => $language_descriptions) {
            foreach ($language_descriptions as $language_id => $description) {
                $title = isset($description['title']) ? trim($description['title']) : '';

                if (empty($description['meta_title']) && $title !== '') {
                    $descriptions[$store_id][$language_id]['meta_title'] = $title;
                }
            }
        }

        $data['module_probg_team_description'] = $descriptions;
        $seo_urls = isset($data['module_probg_team_seo_url']) ? $data['module_probg_team_seo_url'] : array();
        $seo_urls = $this->filterStoreLanguageData($seo_urls, $valid_stores, $valid_languages, true);

        foreach ($descriptions as $store_id => $language_descriptions) {
            if (!isset($seo_urls[$store_id])) {
                $seo_urls[$store_id] = array();
            }

            foreach ($language_descriptions as $language_id => $description) {
                $title = isset($description['title']) ? trim($description['title']) : '';
                $keyword = isset($seo_urls[$store_id][$language_id]) ? trim($seo_urls[$store_id][$language_id]) : '';

                if ((int)$store_id !== 0 && $title === '') {
                    $title = isset($descriptions[0][$language_id]['title']) ? trim($descriptions[0][$language_id]['title']) : '';
                }

                if ($keyword === '') {
                    $base = ProbgTeamSlug::generate($title);

                    if ($base === '') {
                        $base = 'team';
                    }

                    $keyword = $this->getUniqueSeoKeyword($base, (int)$store_id, (int)$language_id, 'probg_team_section=1');
                }

                $seo_urls[$store_id][$language_id] = $keyword;
            }
        }

        $data['module_probg_team_seo_url'] = $seo_urls;

        return $data;
    }

    public function getDescriptions() {
        $data = array();
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "team_setting_description` ORDER BY store_id ASC, language_id ASC");

        foreach ($query->rows as $row) {
            $data[$row['store_id']][$row['language_id']] = array(
                'title' => $row['title'],
                'description' => $row['description'],
                'meta_title' => $row['meta_title'],
                'meta_description' => $row['meta_description'],
                'meta_keyword' => $row['meta_keyword']
            );
        }

        return $data;
    }

    public function saveDescriptions($descriptions) {
        if ($descriptions && $this->isLanguageIndexedDescriptionArray($descriptions)) {
            $descriptions = array(0 => $descriptions);
        }

        $descriptions = $this->filterStoreLanguageData($descriptions, $this->getValidStoreIds(), $this->getValidLanguageIds());
        $this->db->query("DELETE FROM `" . DB_PREFIX . "team_setting_description`");

        foreach ($descriptions as $store_id => $language_descriptions) {
            foreach ($language_descriptions as $language_id => $description) {
                $title = isset($description['title']) ? trim($description['title']) : '';
                $meta_title = isset($description['meta_title']) ? trim($description['meta_title']) : '';

                if ((int)$store_id !== 0 && $title === '') {
                    continue;
                }

                if ($meta_title === '') {
                    $meta_title = $title;
                }

                $this->db->query("INSERT INTO `" . DB_PREFIX . "team_setting_description` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', title = '" . $this->db->escape($title) . "', description = '" . $this->db->escape(isset($description['description']) ? $description['description'] : '') . "', meta_title = '" . $this->db->escape($meta_title) . "', meta_description = '" . $this->db->escape(isset($description['meta_description']) ? $description['meta_description'] : '') . "', meta_keyword = '" . $this->db->escape(isset($description['meta_keyword']) ? $description['meta_keyword'] : '') . "'");
            }
        }

        $this->rotateCacheVersion();
    }

    public function getSeoUrls() {
        $data = array();
        $query = $this->db->query("SELECT store_id, language_id, keyword FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1'");

        foreach ($query->rows as $row) {
            $data[$row['store_id']][$row['language_id']] = $row['keyword'];
        }

        return $data;
    }

    public function saveSeoUrls($seo_urls, $descriptions = array()) {
        if ($descriptions && $this->isLanguageIndexedDescriptionArray($descriptions)) {
            $descriptions = array(0 => $descriptions);
        }

        $valid_stores = $this->getValidStoreIds();
        $valid_languages = $this->getValidLanguageIds();
        $descriptions = $this->filterStoreLanguageData($descriptions, $valid_stores, $valid_languages);
        $seo_urls = $this->filterStoreLanguageData($seo_urls, $valid_stores, $valid_languages, true);
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1'");

        foreach ($descriptions as $store_id => $language_descriptions) {
            if (!isset($seo_urls[$store_id])) {
                $seo_urls[$store_id] = array();
            }

            foreach ($language_descriptions as $language_id => $description) {
                $title = isset($description['title']) ? trim($description['title']) : '';
                $keyword = isset($seo_urls[$store_id][$language_id]) ? trim($seo_urls[$store_id][$language_id]) : '';

                if ((int)$store_id !== 0 && $title === '') {
                    $title = isset($descriptions[0][$language_id]['title']) ? trim($descriptions[0][$language_id]['title']) : '';
                }

                if ($keyword === '') {
                    $keyword = ProbgTeamSlug::generate($title);

                    if ($keyword === '') {
                        $keyword = 'team';
                    }

                    $keyword = $this->getUniqueSeoKeyword($keyword, (int)$store_id, (int)$language_id, 'probg_team_section=1');
                }

                $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'probg_team_section=1', keyword = '" . $this->db->escape($keyword) . "'");
            }
        }

        $this->rotateCacheVersion();
    }

    public function getDiagnostics() {
        $tables = array();
        $database_ok = true;

        foreach ($this->schema_tables as $table) {
            $exists = $this->tableExists($table);
            $tables[$table] = $exists;
            $database_ok = $database_ok && $exists;
        }

        $working_hours = $database_ok && $this->columnExists('team_member_description', 'working_hours');
        $modification = $this->db->query("SELECT modification_id, status FROM `" . DB_PREFIX . "modification` WHERE code = 'probg_team' ORDER BY modification_id DESC LIMIT 1");
        $modification_installed = (bool)$modification->num_rows;
        $modification_enabled = $modification_installed && !empty($modification->row['status']);
        $category_total = $this->tableExists('team_category') ? (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_category`")->row['total'] : 0;
        $member_total = $this->tableExists('team_member') ? (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_member`")->row['total'] : 0;
        $orphan_members = 0;
        $invalid_member_stores = 0;

        if ($this->tableExists('team_member') && $this->tableExists('team_category')) {
            $orphan_members = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_member` m LEFT JOIN `" . DB_PREFIX . "team_category` c ON (m.team_category_id = c.team_category_id) WHERE c.team_category_id IS NULL")->row['total'];
        }

        if ($this->tableExists('team_member_to_store') && $this->tableExists('team_category_to_store') && $this->tableExists('team_member')) {
            $invalid_member_stores = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "team_member_to_store` m2s INNER JOIN `" . DB_PREFIX . "team_member` m ON (m2s.team_member_id = m.team_member_id) LEFT JOIN `" . DB_PREFIX . "team_category_to_store` c2s ON (m.team_category_id = c2s.team_category_id AND m2s.store_id = c2s.store_id) WHERE c2s.team_category_id IS NULL")->row['total'];
        }

        $section_seo_total = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1'")->row['total'];
        $expected_section_seo = count($this->getValidStoreIds()) * count($this->getValidLanguageIds());

        return array(
            'database_ok' => $database_ok,
            'tables' => $tables,
            'working_hours' => $working_hours,
            'modification_installed' => $modification_installed,
            'modification_enabled' => $modification_enabled,
            'category_total' => $category_total,
            'member_total' => $member_total,
            'orphan_members' => $orphan_members,
            'invalid_member_stores' => $invalid_member_stores,
            'section_seo_total' => $section_seo_total,
            'expected_section_seo' => $expected_section_seo
        );
    }

    private function getSchemaVersion() {
        $query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = 'module_probg_team_schema_version' LIMIT 1");

        return $query->num_rows ? (string)$query->row['value'] : '';
    }

    private function setSchemaVersion($version) {
        $query = $this->db->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = 'module_probg_team_schema_version' LIMIT 1");

        if ($query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->db->escape($version) . "', serialized = '0' WHERE setting_id = '" . (int)$query->row['setting_id'] . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', code = 'module_probg_team', `key` = 'module_probg_team_schema_version', `value` = '" . $this->db->escape($version) . "', serialized = '0'");
        }
    }

    public function rotateCacheVersion() {
        $this->cache->set('probg_team.version', str_replace('.', '', sprintf('%.6F', microtime(true))));
    }

    public function getUniqueSeoKeyword($base, $store_id, $language_id, $exclude_query) {
        $base = ProbgTeamSlug::generate($base);

        if ($base === '') {
            $base = 'team';
        }

        $keyword = $base;
        $suffix = 2;

        while ($this->isSeoKeywordUsed($keyword, $store_id, $language_id, $exclude_query)) {
            $keyword = $base . '-' . $suffix;
            $suffix++;
        }

        return $keyword;
    }

    public function isSeoKeywordUsed($keyword, $store_id, $language_id, $exclude_query) {
        $query = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` WHERE keyword = '" . $this->db->escape($keyword) . "' AND store_id = '" . (int)$store_id . "' AND language_id = '" . (int)$language_id . "' AND query != '" . $this->db->escape($exclude_query) . "' LIMIT 1");

        return (bool)$query->num_rows;
    }

    public function getValidStoreIds() {
        $stores = array(0);
        $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store` ORDER BY store_id ASC");

        foreach ($query->rows as $row) {
            $stores[] = (int)$row['store_id'];
        }

        return array_values(array_unique($stores));
    }

    public function getValidLanguageIds() {
        $languages = array();
        $query = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` ORDER BY sort_order ASC, name ASC");

        foreach ($query->rows as $row) {
            $languages[] = (int)$row['language_id'];
        }

        return $languages;
    }

    private function filterStoreLanguageData($data, $valid_stores, $valid_languages, $scalar_values = false) {
        $filtered = array();

        foreach ((array)$data as $store_id => $language_data) {
            $store_id = (int)$store_id;

            if (!in_array($store_id, $valid_stores, true) || !is_array($language_data)) {
                continue;
            }

            foreach ($language_data as $language_id => $value) {
                $language_id = (int)$language_id;

                if (!in_array($language_id, $valid_languages, true)) {
                    continue;
                }

                $filtered[$store_id][$language_id] = $scalar_values ? (string)$value : (array)$value;
            }
        }

        return $filtered;
    }

    private function tableExists($table) {
        $pattern = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), DB_PREFIX . $table);
        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($pattern) . "'");

        return (bool)$query->num_rows;
    }

    private function columnExists($table, $column) {
        if (!$this->tableExists($table)) {
            return false;
        }

        $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . $table . "` WHERE Field = '" . $this->db->escape($column) . "'");

        return (bool)$query->num_rows;
    }

    private function isLanguageIndexedDescriptionArray($descriptions) {
        $first = reset($descriptions);

        return is_array($first) && array_key_exists('title', $first);
    }
}
