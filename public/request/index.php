<?php
require __DIR__."/../.private/scripts/index.php";

$header_ignore = Settings::$get->headers_blacklist;
$get_ignore = ["__method__"];
$mono_api_domain = Settings::$get->api_domain;
$method = "/".$_GET["__method__"];

$headers = getallheaders();
$get = $_GET;

foreach ($header_ignore as $value) {
    if (isset($headers[$value])) unset($headers[$value]);
}

foreach ($get_ignore as $value) {
    if (isset($get[$value])) unset($get[$value]);
}

$headers["x-request-id"] = $headers["x-request-id"] ?: $headers["X-Request-Id"];

if (isset($headers["x-request-id"])) {
    $db = DB::get();
    $st = $db->prepare("SELECT * from `tokens` WHERE `token` = ?");
    $st->execute([$headers["x-request-id"]]);
    $r = $st->fetch();

    if (!($r["id"] > 0)) exit(json(["error" => "Invalid Mono-associated token"]));
    $timer = time();

    unset($headers["x-request-id"]);
    $headers["X-Request-Id"] = $r["mono"];
    $headers["X-Sign"] = mono_sign($method, $r["mono"], $timer);
    $headers["X-Key-Id"] = KEY_ID;
    $headers["X-Time"] = strval($timer);
    // mono_sender($method, "GET", $r["mono"]);
}

$headers_compiled = [];

foreach ($headers as $key => $value) {
    $headers_compiled[] = "$key: $value";
}


$context = stream_context_create([
    "http" => [
        "ignore_errors" => true,
        "header" => $headers_compiled,
        "content" => file_get_contents("php://input"),
        "method" => $_SERVER['REQUEST_METHOD'],
    ]
]);

$request = file_get_contents("$mono_api_domain$method".(count($get) > 0 ? "?" : "").http_build_query($get), false, $context);

function getHttpCode($http_response_header)
{
    if(is_array($http_response_header))
    {
        $parts=explode(' ',$http_response_header[0]);
        if(count($parts)>1)
            return intval($parts[1]);
    }
    return 0;
}

foreach ($http_response_header as $value) {
    header("$value");
}

echo ($request);