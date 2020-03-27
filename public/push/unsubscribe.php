<?php
$__push_mode = true;
require __DIR__ . "/../.private/scripts/index.php";

Token::$is = checkToken(true);

$channels = json_decode($_POST["channels"], true);

if (!is_array($channels)) exit(json(["error" => "Invalid channels list"]));
if (!isset($_POST["endpoint"]) || strlen($_POST["endpoint"]) === 0) exit(json(["error" => "Invalid endpoint"]));

$endpoint = $_POST["endpoint"];

foreach ($channels as $channel) {
    if (
        gettype($channel["type"]) !== "string"
        || gettype($channel["id"]) !== "string"
        || strlen($channel["type"]) === 0
        || strlen($channel["id"]) === 0
    ) {
        exit(json(["error" => "Invalid channels list"]));
    }
}


$db = DB::get();
$ids = [];
foreach ($channels as $channel) {
    $req = $db->prepare("SELECT * from `subscriptions` WHERE `token` = ? AND `type` = ? AND `identificator` = ? AND `endpoint` = ?");
    $req->execute([Token::$is, $channel["type"], $channel["id"], $endpoint]);
    $res = $req->fetch();
    if ($res["id"] > 0) $ids[] = $res["id"];
}

if (count($ids) === 0) die(json(["result" => true]));

$filler = implode(", ", array_fill(0, count($ids), "?"));

$req = $db->prepare("DELETE from `subscriptions` WHERE `id` IN ($filler)");
$r = $req->execute($ids);

if (!$r) die(json(["error" => "Request failed"]));

die(json(["result" => true]));