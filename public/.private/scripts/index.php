<?php
define('debug', $__force_debug ?: false);
require "error_handler.php";

try {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    if (!class_exists("Minishlink\WebPush\WebPush")) throw new Exception("No WebPush library");
    require_once "settings.php";
    require_once __DIR__ . '/push.php';
    Push::setup();

    $__push_loaded = true;
} catch (Exception $e) {
    $__push_loaded = false;
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) && $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'GET') {
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit;
}


header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once "json.php";
require_once "db.php";
require_once "token.php";
require_once "mono-sender.php";

if ($__push_mode && !$__push_loaded) die(json(["error" => "Pushes are not supported on this server"]));
