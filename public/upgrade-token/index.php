<?php
ob_end_clean();
header("Connection: close");
ignore_user_abort(true);
ob_start();
require __DIR__ . "/../.private/scripts/index.php";
list($proof, $roll_in_token) = explode("/", $_GET["token"]);
$all_headers = getallheaders();
$request_id = $all_headers["x-request-id"] ?? $all_headers["X-Request-Id"];


if (debug) {
        try {
            $dat = "TOKEN UPGRADE ".$proof." ".$roll_in_token." ".$request_id."\n\nHEADERS: ".print_r($all_headers, true).PHP_EOL;
            $fp = fopen(dirname(__FILE__)."/../.private/meta/log.txt", 'a');
            fwrite($fp, $dat);
        } catch (Error $e) {
            
        }
    }

if (!is_string($request_id) || !(strlen($request_id) > 0)) die(json(["error" => "Incorrect request ID"]));


$db = DB::get();


$st = $db->prepare("SELECT `id` from `roll-in` WHERE `proof` = :proof AND `token` = :token AND `time` > NOW() - INTERVAL 15 minute");
$st->execute([
    "proof" => $proof,
    "token" => $roll_in_token
]);
$id = $st->fetchColumn();
if (!$id > 0) die(json(["error" => "Incorrect proof or roll token"]));

while (!$isUnique) {
    $token = bin2hex(random_bytes(20));
    $st = $db->prepare("SELECT COUNT(*) from `roll-in` WHERE `token` = ?");
    $st->execute([$token]);
    if ($st->fetchColumn() == 0) $isUnique = true;
}

Token::$is = $token;

$client_data = json_decode(mono_sender("/personal/client-info", "GET", $request_id));

$values = [
    "token" => $token,
    "mono" => $request_id,
    "roll" => $roll_in_token,
    "mono_id" => $client_data->clientId,
];
$str_values = DB::values($values);
$st = $db->prepare("INSERT into `tokens` SET $str_values");
$success = $st->execute($values);
if (!$success) die(json(["error" => "Failed to apply auth data"]));

$st = $db->prepare("UPDATE `tokens` SET `mono` = :token WHERE `mono_id` = :mono_id");
$success = $st->execute(["token" => $request_id, "mono_id" => $client_data->clientId]);
if (!$success) die(json(["error" => "Failed to update other devices"]));

$st = $db->prepare("DELETE from `roll-in` WHERE `token` = :token");
$success = $st->execute(["token" => $roll_in_token]);
if (!$success) die(json(["error" => "Failed to replace auth data"]));

echo json(["ok" => true]);
$size = ob_get_length();
header("Content-Length: $size");
ob_end_flush();
flush();

$cl = $db->prepare("DELETE FROM `roll-in` WHERE `time` < NOW() - INTERVAL 15 minute");
$cl->execute();
$cl = $db->prepare("DELETE FROM `tokens` WHERE `time` < NOW() - INTERVAL 15 minute AND `roll` IS NOT NULL");
$cl->execute();
