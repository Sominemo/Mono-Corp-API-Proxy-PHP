<?php

require "mono-sign.php";

define("KEY_ID", file_get_contents(dirname(__FILE__) . "/../meta/key-id"));

function mono_sender($method, $requestMethod = "GET", $token = null, $data = [], $headers = [], $t = "sp")
{
    $domain = Settings::$get->api_domain;
    $time = time();

    $headers = array_merge(
        [
            "X-Time: $time",
            "X-Sign: " . mono_sign($method, ($token ? $token : $t), $time),
            "X-Key-Id: " . KEY_ID,
        ],
        (is_string($token) ? ["X-Request-Id: $token"] : []),
        $headers
    );

    $options = [
        "http" => [
            "ignore_errors" => true,
            "method" => $requestMethod,
            "header" => $headers,
            "data" => http_build_query($data)
        ]
    ];
    $constext = stream_context_create($options);
    $response = file_get_contents($domain . $method, false, $constext);
    if (debug) {
        print_r($options);
        print_r($http_response_header);
        print_r($response);
    }
    return $response;
}
