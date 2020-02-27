<?php
require __DIR__ . "/.private/scripts/index.php";

die(json([
    "proto" => [
        "version" => 1,
        "patch" => 2,
    ],
    "implementation" => [
        "name" => "PHP Mono Corp API Proxy",
        "author" => "Sominemo",
        "homepage" => "https://github.com/Sominemo/Mono-Corp-API-Proxy-PHP",
    ],
    "server" => [
        "push" => [
            "api" => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['SERVER_NAME']}/push",
            "cert" => Settings::$get->push->public,
            "name" => "Sominemo Push Server ({$_SERVER['SERVER_NAME']})",
        ],
        /**
     * "message" => [
     *      "text" => "content",
     *      "link" => "https://example.com"
     * ],
     */
    ]
]));
