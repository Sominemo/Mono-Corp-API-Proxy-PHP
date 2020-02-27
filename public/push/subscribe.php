<?php
require __DIR__ . "/../.private/scripts/index.php";

Token::$is = checkToken(true);

$endpoint = $_POST["endpoint"];
$key = $_POST["key"];
$auth = $_POST["auth"];
$cert = $_POST["cert"];
$type = $_POST["type"];
$id = $_POST["id"];
$expires = $_POST["expires"];

if ($cert !== Settings::$get->push->public) die(json(["error" => "Incorrect key"]));
$db = DB::get();


if ($type === "statement") die(json(["error" => "Statement pushes are not currently supported"]));
if ($type === "sominemo" && $id == "app_updates") {
    $re = $db->prepare("INSERT into `subscriptions` SET `type` = ?, `identificator` = ?, `token` = ?, `date` = ?, `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?");
    $r = $re->execute([$type, $id, Token::$is, time(), $endpoint, $key, $auth, $expires]);
    if (!$r) die(json(["error" => "Request failed"]));
}

$re = $db->prepare("UPDATE `subscriptions` SET `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ? WHERE `token` = ?");
$r = $re->execute([$endpoint, $key, $auth, $expires, Token::$is]);


die(json(["result" => true]));
