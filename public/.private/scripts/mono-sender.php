<?php

require "mono-sign.php";

define("KEY_ID", file_get_contents(dirname(__FILE__) . "/../meta/key-id"));

function mono_sender($method, $requestMethod = "GET", $token = null, $data = "{}", $headers = [], $t = null, &$log = [])
{
    $domain = Settings::$get->api_domain;
    $time = time();
    if ($t === null) $t = "sp";

    $headers = array_merge(
        [
            "X-Time: $time",
            "X-Sign: " . mono_sign($method, ($token ? $token : $t), $time),
            "X-Key-Id: " . KEY_ID,
            "Accept: application/json",
            "Content-Type: application/json",
        ],
        (is_string($token) ? ["X-Request-Id: $token"] : []),
        $headers
    );

    $options = [
        "http" => [
            "ignore_errors" => true,
            "method" => $requestMethod,
            "header" => $headers,
            "content" => $data ?: "{}",
        ]
    ];
    $constext = stream_context_create($options);
    $response = file_get_contents($domain . $method, false, $constext);

    if (debug) {
    $log[] = "\n###### API CALL ######\n";
    $log[] = "------ REQUEST -------\n";
    $log[] = print_r("URL: " . $method . "\n", true);
    $log[] = ("HTTP: " . print_r($options, true) . "\n");
    $log[] = print_r("SIGN FORMULA: " . $method . ($token ? $token : $t) . $time . "\n", true);
    $log[] = "\n------ RESPONSE -------\n";
    $log[] = "HEADERS: " . print_r($http_response_header, true) . "\n";
    $log[] = print_r("BODY: \n" . $response . "\n", true);
    $log[] = "#### END API CALL ####\n";

        foreach ($log as $value) {
            echo $value;
        try {
            $dat = $value.PHP_EOL;
            $fp = fopen(dirname(__FILE__)."/../meta/log.txt", 'a');
            fwrite($fp, $dat);
        } catch (Error $e) {
            
        }
        }
    }
    return $response;
}
