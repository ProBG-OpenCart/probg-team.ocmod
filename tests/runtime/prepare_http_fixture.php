<?php
if ($argc < 8) {
    fwrite(STDERR, "Usage: php prepare_http_fixture.php <opencart-upload> <db-host> <db-user> <db-pass> <db-name> <db-port> <base-url>\n");
    exit(2);
}

$core_root = rtrim(str_replace('\\', '/', realpath($argv[1])), '/') . '/';
$db_host = $argv[2];
$db_user = $argv[3];
$db_pass = $argv[4];
$db_name = $argv[5];
$db_port = (int)$argv[6];
$base_url = rtrim($argv[7], '/') . '/';

function smoke_fail($message) {
    fwrite(STDERR, "ERROR: " . $message . "\n");
    exit(1);
}

if (!$core_root || !is_file($core_root . 'system/startup.php')) {
    smoke_fail('OpenCart root is invalid');
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($mysqli->connect_errno) {
    smoke_fail('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');
$mysqli->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

define('DIR_SYSTEM', $core_root . 'system/');
define('DB_PREFIX', 'oc_');

class HttpSmokeResult {
    public $num_rows = 0;
    public $row = array();
    public $rows = array();
}

class HttpSmokeDB {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function query($sql) {
        $result = $this->mysqli->query($sql);
        if ($result === false) {
            throw new RuntimeException($this->mysqli->error . "\nSQL: " . $sql);
        }

        $output = new HttpSmokeResult();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $output->rows[] = $row;
            }
            $output->num_rows = count($output->rows);
            $output->row = $output->num_rows ? $output->rows[0] : array();
            $result->free();
        }
        return $output;
    }

    public function escape($value) {
        return $this->mysqli->real_escape_string((string)$value);
    }

    public function getLastId() {
        return $this->mysqli->insert_id;
    }
}

class HttpSmokeCache {
    public function set($key, $value) {}
    public function get($key) { return false; }
    public function delete($key) {}
}

class Model {
    public $db;
    public $cache;
}

require_once($core_root . 'admin/model/extension/module/probg_team.php');

$db = new HttpSmokeDB($mysqli);
$model = new ModelExtensionModuleProbgTeam();
$model->db = $db;
$model->cache = new HttpSmokeCache();
$model->install();

function set_setting($db, $code, $key, $value) {
    $db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND code = '" . $db->escape($code) . "' AND `key` = '" . $db->escape($key) . "'");
    $db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, code = '" . $db->escape($code) . "', `key` = '" . $db->escape($key) . "', `value` = '" . $db->escape($value) . "', serialized = 0");
}

$module_settings = array(
    'module_probg_team_status' => '1',
    'module_probg_team_limit' => '12',
    'module_probg_team_show_empty_categories' => '1',
    'module_probg_team_show_telephone' => '1',
    'module_probg_team_show_city' => '1',
    'module_probg_team_show_working_hours' => '1',
    'module_probg_team_show_website' => '1',
    'module_probg_team_show_social' => '1',
    'module_probg_team_list_width' => '240',
    'module_probg_team_list_height' => '240',
    'module_probg_team_member_width' => '500',
    'module_probg_team_member_height' => '500',
    'module_probg_team_gallery_width' => '180',
    'module_probg_team_gallery_height' => '180',
    'module_probg_team_open_graph_status' => '1',
    'module_probg_team_schema_status' => '1',
    'module_probg_team_cache_status' => '0',
    'module_probg_team_sitemap_status' => '1'
);

foreach ($module_settings as $key => $value) {
    set_setting($db, 'module_probg_team', $key, $value);
}

set_setting($db, 'config', 'config_url', $base_url);
set_setting($db, 'config', 'config_ssl', $base_url);
set_setting($db, 'config', 'config_seo_url', '0');

$language = $db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE status = 1 ORDER BY sort_order ASC, language_id ASC LIMIT 1");
if (!$language->num_rows) {
    smoke_fail('No active OpenCart language was found');
}
$language_id = (int)$language->row['language_id'];

$db->query("DELETE FROM `" . DB_PREFIX . "team_setting_description` WHERE store_id = 0 AND language_id = '" . $language_id . "'");
$db->query("INSERT INTO `" . DB_PREFIX . "team_setting_description` SET store_id = 0, language_id = '" . $language_id . "', title = 'Runtime Team', description = '<p>Runtime Team section description</p>', meta_title = 'Runtime Team', meta_description = 'Runtime Team meta description', meta_keyword = 'runtime,team'");

$db->query("INSERT INTO `" . DB_PREFIX . "team_category` SET sort_order = 1, status = 1, date_added = NOW(), date_modified = NOW()");
$category_id = (int)$db->getLastId();
$db->query("INSERT INTO `" . DB_PREFIX . "team_category_description` SET team_category_id = '" . $category_id . "', language_id = '" . $language_id . "', name = 'Runtime Category', description = '<p>Runtime Category description</p>', meta_title = 'Runtime Category', meta_description = 'Runtime Category meta description', meta_keyword = 'runtime,category'");
$db->query("INSERT INTO `" . DB_PREFIX . "team_category_to_store` SET team_category_id = '" . $category_id . "', store_id = 0");

$db->query("INSERT INTO `" . DB_PREFIX . "team_member` SET team_category_id = '" . $category_id . "', image = '', sort_order = 1, status = 1, date_added = NOW(), date_modified = NOW()");
$member_id = (int)$db->getLastId();
$db->query("INSERT INTO `" . DB_PREFIX . "team_member_description` SET team_member_id = '" . $member_id . "', language_id = '" . $language_id . "', name = 'Runtime Member', short_description = '<p>Runtime short description</p>', description = '<p>Runtime full description</p>', telephone = '+359 2 123 4567', city = 'Sofia', working_hours = 'Mon-Fri 09:00-18:00', website = 'https://example.com', facebook = '', instagram = '', youtube = '', linkedin = '', meta_title = 'Runtime Member', meta_description = 'Runtime Member meta description', meta_keyword = 'runtime,member'");
$db->query("INSERT INTO `" . DB_PREFIX . "team_member_to_store` SET team_member_id = '" . $member_id . "', store_id = 0");

$db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE `query` = 'probg_team_section=1' OR `query` = 'probg_team_category_id=" . $category_id . "' OR `query` = 'probg_team_member_id=" . $member_id . "'");
$db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = 0, language_id = '" . $language_id . "', `query` = 'probg_team_section=1', keyword = 'runtime-team'");
$db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = 0, language_id = '" . $language_id . "', `query` = 'probg_team_category_id=" . $category_id . "', keyword = 'runtime-category'");
$db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = 0, language_id = '" . $language_id . "', `query` = 'probg_team_member_id=" . $member_id . "', keyword = '" . $member_id . "-runtime-member'");

print("CATEGORY_ID=" . $category_id . "\n");
print("MEMBER_ID=" . $member_id . "\n");
print("LANGUAGE_ID=" . $language_id . "\n");
