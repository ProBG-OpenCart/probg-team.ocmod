<?php
if ($argc < 7) {
    fwrite(STDERR, "Usage: php register_ocmod.php <db-host> <db-user> <db-pass> <db-name> <db-port> <install.xml>\n");
    exit(2);
}

$db_host = $argv[1];
$db_user = $argv[2];
$db_pass = $argv[3];
$db_name = $argv[4];
$db_port = (int)$argv[5];
$xml_path = $argv[6];

function fail_test($message) {
    fwrite(STDERR, "ERROR: " . $message . "\n");
    exit(1);
}

if (!is_file($xml_path)) {
    fail_test('install.xml was not found: ' . $xml_path);
}

$xml = file_get_contents($xml_path);
$dom = new DOMDocument('1.0', 'UTF-8');
if (!$dom->loadXML($xml)) {
    fail_test('install.xml is not valid XML');
}

function xml_text($dom, $name) {
    $nodes = $dom->getElementsByTagName($name);
    return $nodes->length ? trim($nodes->item(0)->textContent) : '';
}

$name = xml_text($dom, 'name');
$code = xml_text($dom, 'code');
$author = xml_text($dom, 'author');
$version = xml_text($dom, 'version');
$link = xml_text($dom, 'link');

if ($code !== 'probg_team' || $name === '' || $version === '') {
    fail_test('Unexpected modification metadata');
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($mysqli->connect_errno) {
    fail_test('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

$escape = function ($value) use ($mysqli) {
    return $mysqli->real_escape_string((string)$value);
};

$mysqli->query("DELETE FROM `oc_modification` WHERE `code` = '" . $escape($code) . "'");
$sql = "INSERT INTO `oc_modification` SET extension_install_id = 0, name = '" . $escape($name) . "', code = '" . $escape($code) . "', author = '" . $escape($author) . "', version = '" . $escape($version) . "', link = '" . $escape($link) . "', xml = '" . $escape($xml) . "', status = 1, date_added = NOW()";
if (!$mysqli->query($sql)) {
    fail_test('Could not register modification: ' . $mysqli->error);
}

function set_setting($mysqli, $code, $key, $value) {
    $code = $mysqli->real_escape_string($code);
    $key = $mysqli->real_escape_string($key);
    $value = $mysqli->real_escape_string($value);
    $mysqli->query("DELETE FROM `oc_setting` WHERE store_id = 0 AND code = '" . $code . "' AND `key` = '" . $key . "'");
    if (!$mysqli->query("INSERT INTO `oc_setting` SET store_id = 0, code = '" . $code . "', `key` = '" . $key . "', `value` = '" . $value . "', serialized = 0")) {
        fail_test('Could not set ' . $key . ': ' . $mysqli->error);
    }
}

set_setting($mysqli, 'config', 'config_seo_url', '1');
set_setting($mysqli, 'feed_google_sitemap', 'feed_google_sitemap_status', '1');

$result = $mysqli->query("SELECT modification_id, status, version FROM `oc_modification` WHERE code = 'probg_team' LIMIT 1");
if (!$result || !$result->num_rows) {
    fail_test('Registered modification could not be read back');
}
$row = $result->fetch_assoc();
if ((int)$row['status'] !== 1 || $row['version'] !== $version) {
    fail_test('Registered modification metadata does not match');
}

print("MODIFICATION_ID=" . (int)$row['modification_id'] . "\n");
print("MODIFICATION_VERSION=" . $version . "\n");
