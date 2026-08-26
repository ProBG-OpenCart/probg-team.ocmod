<?php
if ($argc < 7) {
    fwrite(STDERR, "Usage: php install_model_smoke.php <opencart-upload> <db-host> <db-user> <db-pass> <db-name> <db-port>\n");
    exit(2);
}

$core_root = rtrim(str_replace('\\', '/', realpath($argv[1])), '/') . '/';
$db_host = $argv[2];
$db_user = $argv[3];
$db_pass = $argv[4];
$db_name = $argv[5];
$db_port = (int)$argv[6];

function test_fail($message) {
    fwrite(STDERR, "ERROR: " . $message . "\n");
    exit(1);
}

function test_assert($condition, $message) {
    if (!$condition) {
        test_fail($message);
    }
}

if (!$core_root || !is_file($core_root . 'install/opencart.sql')) {
    test_fail('OpenCart install/opencart.sql was not found');
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($mysqli->connect_errno) {
    test_fail('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');
// Match the OpenCart 3 MySQLi driver session so the stock installer SQL is
// exercised under the same SQL mode as the application runtime.
$mysqli->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

$sql_file = $core_root . 'install/opencart.sql';
$lines = file($sql_file);
$sql = '';
foreach ($lines as $line) {
    if (!$line || substr($line, 0, 2) === '--' || substr($line, 0, 1) === '#') {
        continue;
    }
    $sql .= $line;
    if (preg_match('/;\s*$/', $line)) {
        try {
            $mysqli->query($sql);
        } catch (mysqli_sql_exception $exception) {
            test_fail('OpenCart schema import failed: ' . $exception->getMessage() . "\nSQL: " . $sql);
        }
        $sql = '';
    }
}

define('DIR_SYSTEM', $core_root . 'system/');
define('DB_PREFIX', 'oc_');

class SmokeResult {
    public $num_rows = 0;
    public $row = array();
    public $rows = array();
}

class SmokeDB {
    private $mysqli;

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    public function query($sql) {
        try {
            $result = $this->mysqli->query($sql);
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException($exception->getMessage() . "\nSQL: " . $sql, 0, $exception);
        }

        if ($result === false) {
            throw new RuntimeException($this->mysqli->error . "\nSQL: " . $sql);
        }

        $output = new SmokeResult();
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

class SmokeCache {
    public $values = array();
    public function set($key, $value) {
        $this->values[$key] = $value;
    }
    public function get($key) {
        return isset($this->values[$key]) ? $this->values[$key] : false;
    }
    public function delete($key) {
        unset($this->values[$key]);
    }
}

class Model {
    public $db;
    public $cache;
}

require_once($core_root . 'admin/model/extension/module/probg_team.php');

$db = new SmokeDB($mysqli);
$cache = new SmokeCache();
$model = new ModelExtensionModuleProbgTeam();
$model->db = $db;
$model->cache = $cache;

$tables = array(
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

$model->install();

foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '" . DB_PREFIX . $table . "'");
    test_assert($result->num_rows === 1, 'Missing table after install: ' . DB_PREFIX . $table);
}

$version = $db->query("SELECT value FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND code = 'module_probg_team' AND `key` = 'module_probg_team_version' LIMIT 1");
test_assert($version->num_rows === 1 && $version->row['value'] === ModelExtensionModuleProbgTeam::VERSION, 'Module version setting was not written');

$schema = $db->query("SELECT value FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND code = 'module_probg_team' AND `key` = 'module_probg_team_schema_version' LIMIT 1");
test_assert($schema->num_rows === 1 && $schema->row['value'] === ModelExtensionModuleProbgTeam::SCHEMA_VERSION, 'Schema version setting was not written');

$model->install();
$duplicates = $db->query("SELECT `key`, COUNT(*) AS total FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND code = 'module_probg_team' GROUP BY `key` HAVING COUNT(*) > 1");
test_assert($duplicates->num_rows === 0, 'Repeated install created duplicate settings');

$db->query("ALTER TABLE `" . DB_PREFIX . "team_member_description` DROP COLUMN `working_hours`");
$db->query("UPDATE `" . DB_PREFIX . "setting` SET value = '0.3.0' WHERE store_id = 0 AND code = 'module_probg_team' AND `key` = 'module_probg_team_schema_version'");
$model->upgrade(false);
$working_hours = $db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "team_member_description` WHERE Field = 'working_hours'");
test_assert($working_hours->num_rows === 1, 'Upgrade did not restore working_hours');
test_assert(isset($working_hours->row['Null']) && strtoupper($working_hours->row['Null']) === 'NO', 'working_hours must be NOT NULL after migration');

$db->query("INSERT INTO `" . DB_PREFIX . "team_category` SET sort_order = 0, status = 1, date_added = NOW(), date_modified = NOW()");
$category_id = $db->getLastId();
$db->query("INSERT INTO `" . DB_PREFIX . "team_member` SET team_category_id = '" . (int)$category_id . "', image = '', sort_order = 0, status = 1, date_added = NOW(), date_modified = NOW()");
$member_id = $db->getLastId();
$model->upgrade(true);

$category_store = $db->query("SELECT store_id FROM `" . DB_PREFIX . "team_category_to_store` WHERE team_category_id = '" . (int)$category_id . "' AND store_id = 0");
$member_store = $db->query("SELECT store_id FROM `" . DB_PREFIX . "team_member_to_store` WHERE team_member_id = '" . (int)$member_id . "' AND store_id = 0");
test_assert($category_store->num_rows === 1, 'Upgrade did not repair category store assignment');
test_assert($member_store->num_rows === 1, 'Upgrade did not repair member store assignment');

$model->uninstall();
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '" . DB_PREFIX . $table . "'");
    test_assert($result->num_rows === 0, 'Table remained after uninstall: ' . DB_PREFIX . $table);
}
test_assert(isset($cache->values['probg_team.version']), 'Uninstall did not rotate the Team cache namespace');

print("Runtime database smoke test OK\n");
