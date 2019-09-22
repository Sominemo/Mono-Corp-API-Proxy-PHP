<?
ob_end_clean();
header("Connection: close");
ignore_user_abort(true);
ob_start();
require __DIR__."../.private/scripts/index.php";
list($proof, $roll_in_token) = explode("/", $_GET["token"]);
$request_id = getallheaders()["x-request-id"];
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

$values = [
    "token" => $token,
    "mono" => $request_id,
    "roll" => $roll_in_token,
];
$str_values = DB::values($values);
$st = $db->prepare("INSERT into `tokens` SET $str_values");
$success = $st->execute($values);
if (!$success) die(json(["error" => "Failed to apply auth data"]));

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