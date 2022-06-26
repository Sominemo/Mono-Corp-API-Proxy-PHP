<?php
require __DIR__ . "/.private/scripts/index.php";

$proto = [
    "proto" => [
        "version" => 1,
        "patch" => 4,
    ],
    "implementation" => [
        "name" => "PHP Mono Corp API Proxy",
        "author" => "Sominemo",
        "homepage" => "https://github.com/Sominemo/Mono-Corp-API-Proxy-PHP",
    ],
    "server" => [
        /**
     * "message" => [
     *      "text" => "content",
     *      "link" => "https://example.com"
     * ],
     */
    ]
];

if ($__push_loaded) $proto["server"]["push"] =
    [
        "api" => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['SERVER_NAME']}/push",
        "cert" => Settings::$get->push->public,
        "name" => "Sominemo Push Server ({$_SERVER['SERVER_NAME']})",
    ];

die(json($proto));
