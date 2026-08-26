<?php
$document_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$target = $document_root . '/' . ltrim($path, '/');

if ($path !== '/' && is_file($target)) {
    return false;
}

$route = ltrim(rawurldecode((string)$path), '/');
if ($route !== '') {
    $_GET['_route_'] = $route;
    $_REQUEST['_route_'] = $route;
}

chdir($document_root);
require $document_root . '/index.php';
