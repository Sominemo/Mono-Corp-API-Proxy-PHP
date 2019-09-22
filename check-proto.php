<?php
require __DIR__ . "/.private/scripts/index.php";

die(json([
    "proto" => [
        "version" => 1,
        "patch" => 0,
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
         * ]
         */
    ]
]));
