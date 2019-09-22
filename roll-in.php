<?php
ob_end_clean();
header("Connection: close");
ignore_user_abort(true);
ob_start();
require __DIR__."/.private/scripts/index.php";

$db = DB::get();

$isUnique = false;
while (!$isUnique) {
    $token = bin2hex(random_bytes(20));
    $st = $db->prepare("SELECT COUNT(*) from `roll-in` WHERE `token` = ?");
    $st->execute([$token]);
    if ($st->fetchColumn() == 0) $isUnique = true;
}

Token::$is = $token;
$proof = bin2hex(random_bytes(4));

$auth = mono_sender("/personal/auth/request", "POST", null, [], ["X-Callback: " . Settings::$get->path . "/upgrade-token/" . $proof . "/" . $token, "X-Permissions: sp",]);
$authParse = json_decode($auth, true);
if (json_last_error() !== JSON_ERROR_NONE || isset($authParse["errorDescription"])) die(json(["error" => "Failed to request auth from Monobank"]));

$values = [
    "token" => $token,
    "mono_token" => $authParse["tokenRequestId"],
    "mono" => $authParse["acceptUrl"],
    "proof" => $proof,
];
$str_values = DB::values($values);
$st = $db->prepare("INSERT into `roll-in` SET $str_values");
$success = $st->execute($values);
if (!$success) die(json(["error" => "Failed to apply auth data"]));

$qr = file_get_contents(
    "https://chart.googleapis.com/chart?" . http_build_query([
        "cht" => "qr",
        "chs" => "250",
        "chl" => $authParse["acceptUrl"]
    ])
);


echo json([
    "token" => $token,
    "requestId" => $authParse["tokenRequestId"],
    "url" => $authParse["acceptUrl"],
    "qr" => base64_encode($qr)
]);

$size = ob_get_length();
header("Content-Length: $size");
ob_end_flush();
flush();

$cl = $db->prepare("DELETE FROM `roll-in` WHERE `time` < NOW() - INTERVAL 15 minute");
$cl->execute();
$cl = $db->prepare("DELETE FROM `tokens` WHERE `time` < NOW() - INTERVAL 15 minute AND `roll` IS NOT NULL");
$cl->execute();
