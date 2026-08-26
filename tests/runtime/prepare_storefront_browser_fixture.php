<?php
if ($argc < 10) {
    fwrite(STDERR, "Usage: php prepare_storefront_browser_fixture.php <opencart-upload> <db-host> <db-user> <db-pass> <db-name> <db-port> <category-id> <member-id> <theme-mode>\n");
    exit(2);
}

$root = rtrim(str_replace('\\', '/', realpath($argv[1])), '/') . '/';
$db_host = $argv[2];
$db_user = $argv[3];
$db_pass = $argv[4];
$db_name = $argv[5];
$db_port = (int)$argv[6];
$category_id = (int)$argv[7];
$member_id = (int)$argv[8];
$theme_mode = $argv[9];

function fixture_fail($message) {
    fwrite(STDERR, "ERROR: " . $message . "\n");
    exit(1);
}

if (!$root || !is_file($root . 'system/startup.php')) {
    fixture_fail('OpenCart root is invalid');
}

if (!in_array($theme_mode, array('default', 'custom'), true)) {
    fixture_fail('Theme mode must be default or custom');
}

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($mysqli->connect_errno) {
    fixture_fail('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');
$mysqli->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");

function sql_escape($mysqli, $value) {
    return $mysqli->real_escape_string((string)$value);
}

function run_query($mysqli, $sql) {
    $result = $mysqli->query($sql);
    if ($result === false) {
        fixture_fail($mysqli->error . "\nSQL: " . $sql);
    }
    return $result;
}

function set_setting($mysqli, $code, $key, $value, $serialized = 0) {
    $code = sql_escape($mysqli, $code);
    $key = sql_escape($mysqli, $key);
    $value = sql_escape($mysqli, $value);
    run_query($mysqli, "DELETE FROM `oc_setting` WHERE store_id = 0 AND code = '{$code}' AND `key` = '{$key}'");
    run_query($mysqli, "INSERT INTO `oc_setting` SET store_id = 0, code = '{$code}', `key` = '{$key}', `value` = '{$value}', serialized = '" . (int)$serialized . "'");
}

$language_result = run_query($mysqli, "SELECT language_id FROM `oc_language` WHERE status = 1 ORDER BY sort_order ASC, language_id ASC LIMIT 1");
if (!$language_result->num_rows) {
    fixture_fail('No active language found');
}
$language_id = (int)$language_result->fetch_assoc()['language_id'];

$image_dir = $root . 'image/catalog';
if (!is_dir($image_dir) && !mkdir($image_dir, 0777, true) && !is_dir($image_dir)) {
    fixture_fail('Could not create fixture image directory');
}

$image_path = $image_dir . '/probg-team-browser.png';
if (function_exists('imagecreatetruecolor')) {
    $image = imagecreatetruecolor(640, 480);
    imagepng($image, $image_path);
    imagedestroy($image);
} else {
    $fallback = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    file_put_contents($image_path, $fallback);
}

run_query($mysqli, "UPDATE `oc_team_member` SET image = 'catalog/probg-team-browser.png' WHERE team_member_id = '" . $member_id . "'");
run_query($mysqli, "DELETE FROM `oc_team_member_image` WHERE team_member_id = '" . $member_id . "'");
run_query($mysqli, "INSERT INTO `oc_team_member_image` SET team_member_id = '" . $member_id . "', image = 'catalog/probg-team-browser.png', sort_order = 1");

$long_token = str_repeat('ResponsiveTeamToken', 18);
$wide_table = '<table class="table table-bordered" style="min-width:960px"><tr><th>Responsive fixture</th><th>' . $long_token . '</th></tr><tr><td>Layout</td><td>Wide rich content must scroll inside the Team content container without widening the page.</td></tr></table>';
$team_description = '<p>Responsive Team section description.</p>' . $wide_table;
$category_description = '<p>Responsive Team category description.</p>' . $wide_table;
$short_description = '<p>Responsive member short description ' . $long_token . '</p>';
$member_description = '<p>Responsive member full description.</p>' . $wide_table . '<pre>' . $long_token . '</pre>';
$website = 'https://example.com/team/' . strtolower($long_token);

run_query($mysqli, "UPDATE `oc_team_setting_description` SET description = '" . sql_escape($mysqli, $team_description) . "' WHERE store_id = 0 AND language_id = '" . $language_id . "'");
run_query($mysqli, "UPDATE `oc_team_category_description` SET description = '" . sql_escape($mysqli, $category_description) . "' WHERE team_category_id = '" . $category_id . "' AND language_id = '" . $language_id . "'");
run_query($mysqli, "UPDATE `oc_team_member_description` SET short_description = '" . sql_escape($mysqli, $short_description) . "', description = '" . sql_escape($mysqli, $member_description) . "', website = '" . sql_escape($mysqli, $website) . "', facebook = 'https://facebook.com/probg-e2e', instagram = 'https://instagram.com/probg-e2e', youtube = 'https://youtube.com/@probg-e2e', linkedin = 'https://linkedin.com/in/probg-e2e' WHERE team_member_id = '" . $member_id . "' AND language_id = '" . $language_id . "'");

set_setting($mysqli, 'module_probg_team', 'module_probg_team_instances_migrated', '1');
set_setting($mysqli, 'module_probg_team', 'module_probg_team_cache_status', '0');

run_query($mysqli, "DELETE FROM `oc_module` WHERE name IN ('Browser E2E Team Members', 'Browser E2E Team Menu')");

$members_setting = json_encode(array(
    'probg_team_type' => 'members',
    'title' => array($language_id => 'Browser E2E Team Members'),
    'team_category_id' => $category_id,
    'limit' => 6,
    'columns' => 3,
    'sort' => 'sort_order',
    'show_category' => 1,
    'show_city' => 1,
    'show_description' => 1,
    'status' => 1
));
run_query($mysqli, "INSERT INTO `oc_module` SET name = 'Browser E2E Team Members', code = 'probg_team', setting = '" . sql_escape($mysqli, $members_setting) . "'");
$members_module_id = (int)$mysqli->insert_id;

$menu_setting = json_encode(array(
    'probg_team_type' => 'menu',
    'title' => array($language_id => 'Browser E2E Team Menu'),
    'team_category_id' => $category_id,
    'limit' => 10,
    'status' => 1
));
run_query($mysqli, "INSERT INTO `oc_module` SET name = 'Browser E2E Team Menu', code = 'probg_team', setting = '" . sql_escape($mysqli, $menu_setting) . "'");
$menu_module_id = (int)$mysqli->insert_id;

$layout_result = run_query($mysqli, "SELECT layout_id FROM `oc_layout` WHERE name = 'ProBG Team Browser E2E' LIMIT 1");
if ($layout_result->num_rows) {
    $layout_id = (int)$layout_result->fetch_assoc()['layout_id'];
    run_query($mysqli, "DELETE FROM `oc_layout_route` WHERE layout_id = '" . $layout_id . "'");
    run_query($mysqli, "DELETE FROM `oc_layout_module` WHERE layout_id = '" . $layout_id . "'");
} else {
    run_query($mysqli, "INSERT INTO `oc_layout` SET name = 'ProBG Team Browser E2E'");
    $layout_id = (int)$mysqli->insert_id;
}

run_query($mysqli, "INSERT INTO `oc_layout_route` SET layout_id = '" . $layout_id . "', store_id = 0, route = 'extension/probg_team/%'");
run_query($mysqli, "INSERT INTO `oc_layout_module` SET layout_id = '" . $layout_id . "', code = 'probg_team." . $members_module_id . "', position = 'content_top', sort_order = 0");
run_query($mysqli, "INSERT INTO `oc_layout_module` SET layout_id = '" . $layout_id . "', code = 'probg_team." . $menu_module_id . "', position = 'column_left', sort_order = 0");
run_query($mysqli, "DELETE FROM `oc_team_category_to_layout` WHERE team_category_id = '" . $category_id . "' AND store_id = 0");
run_query($mysqli, "INSERT INTO `oc_team_category_to_layout` SET team_category_id = '" . $category_id . "', store_id = 0, layout_id = '" . $layout_id . "'");

if ($theme_mode === 'custom') {
    $custom_common = $root . 'catalog/view/theme/probg_e2e/template/common';
    if (!is_dir($custom_common) && !mkdir($custom_common, 0777, true) && !is_dir($custom_common)) {
        fixture_fail('Could not create custom theme fixture directory');
    }

    $default_header = $root . 'catalog/view/theme/default/template/common/header.twig';
    $custom_header = $custom_common . '/header.twig';
    $header = file_get_contents($default_header);
    if ($header === false) {
        fixture_fail('Could not read default theme header');
    }
    $marker = '<meta name="probg-team-e2e-theme" content="custom" />';
    $header = str_replace('</head>', $marker . "\n</head>", $header);
    file_put_contents($custom_header, $header);

    set_setting($mysqli, 'config', 'config_theme', 'probg_e2e');
    set_setting($mysqli, 'theme_probg_e2e', 'theme_probg_e2e_status', '1');
} else {
    set_setting($mysqli, 'config', 'config_theme', 'default');
}

print('LAYOUT_ID=' . $layout_id . "\n");
print('MEMBERS_MODULE_ID=' . $members_module_id . "\n");
print('MENU_MODULE_ID=' . $menu_module_id . "\n");
print('THEME_MODE=' . $theme_mode . "\n");
