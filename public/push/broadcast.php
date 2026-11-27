<?php
$raw = file_get_contents('php://input');
$input = json_decode($raw);

$__push_mode = true;
$__force_debug = true;
require __DIR__ . "/../.private/scripts/index.php";
if (json_last_error() !== JSON_ERROR_NONE) exit(json(["error" => "Incorrect body"]));

if (($_GET["token"] ?? null) !== Settings::$get->push->proof) {
    http_response_code(403);
    exit(json(["error" => "Incorrect server proof"]));
}

$send_all = array_key_exists("all", $_GET)
    && $_GET["all"] !== "0"
    && strtolower((string)$_GET["all"]) !== "false";

$id = $_GET["id"] ?? null;
$type = $_GET["type"] ?? null;

if (!$send_all && ($type === null || $type === "" || $id === null || $id === "")) {
    http_response_code(400);
    exit(json(["error" => "Missing type/id parameters or all flag"]));
}

$db = DB::get();

if ($send_all) {
    $req = $db->prepare("SELECT `id`, `token`, `endpoint`, `key`, `auth` FROM `subscriptions` WHERE `endpoint` IS NOT NULL AND `endpoint` != '' ORDER BY `date` DESC, `id` DESC");
    $req->execute();
} else {
    $req = $db->prepare("SELECT `id`, `token`, `endpoint`, `key`, `auth` FROM `subscriptions` WHERE `type` = ? AND `identificator` = ? AND `endpoint` IS NOT NULL AND `endpoint` != '' ORDER BY `date` DESC, `id` DESC");
    $req->execute([$type, $id]);
}

$seen_endpoints = [];
$sent = 0;
$failed = 0;

while ($f_row = $req->fetch(PDO::FETCH_ASSOC)) {
    $endpoint = $f_row["endpoint"];
    if (isset($seen_endpoints[$endpoint])) {
        continue;
    }
    $seen_endpoints[$endpoint] = true;

    Token::$is = $f_row["token"];
    try {
        $ok = Push::reply(
            Push::sub(
                $endpoint,
                $f_row["key"],
                $f_row["auth"],
            ),
            $raw,
            false,
            [],
            $f_row["id"],
        );
    } catch (Throwable $e) {
        $ok = false;
    }

    if ($ok) {
        $sent++;
    } else {
        $failed++;
    }
}

echo json([
    "result" => true,
    "sent" => $sent,
    "failed" => $failed,
    "total" => $sent + $failed,
]);
