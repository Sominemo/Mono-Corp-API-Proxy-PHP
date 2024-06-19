<?php
require __DIR__."/.private/scripts/index.php";

$token = checkToken(false);

$db = DB::get();

$mono_id_req = $db->prepare("SELECT `mono_id` FROM `tokens` WHERE `token` = :token");
$success = $mono_id_req->execute(["token" => $token]);
if (!$success) die(json(["error" => "Failed to find user mono_id"]));
$mono_id = $mono_id_req->fetchColumn();

$tokens_req = $db->prepare("SELECT `token` FROM `tokens` WHERE `mono_id` = :mono_id");
$success = $tokens_req->execute(["mono_id" => $mono_id]);
if (!$success) die(json(["error" => "Failed to find user tokens"]));

$tokens = $tokens_req->fetchAll(PDO::FETCH_ASSOC);

for ($i = 0; $i < count($tokens); $i++) {
    $token = $tokens[$i];
    $delete_sub_req = $db->prepare("DELETE FROM `subscriptions` WHERE `token` = :token");
    $success = $delete_sub_req->execute(["token" => $token["token"]]);
    if (!$success) die(json(["error" => "Failed to delete subscriptions"]));
}

$delete_tokens_req = $db->prepare("DELETE FROM `tokens` WHERE `mono_id` = :mono_id");
$success = $delete_tokens_req->execute(["mono_id" => $client_data->clientId]);
if (!$success) die(json(["error" => "Failed to delete tokens"]));

$result = [
    "status" => true,
];

die(json($result));
