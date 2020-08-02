<?php
$raw = file_get_contents('php://input');
$input = json_decode($raw);

$__force_debug = true;
require __DIR__ . "/../.private/scripts/index.php";
if (json_last_error() !== JSON_ERROR_NONE) exit(json(["error" => "Incorrect body"]));

if ($_GET["token"] !== Settings::$get->push->proof) {
    http_response_code(403);
    exit(json(["error" => "Incorrect server proof"]));
}

$id = $_GET["id"];
$type = $_GET["type"];

$db = DB::get();

if (!$_GET["all"]) {
    $req = $db->prepare("SELECT * FROM subscriptions WHERE subscriptions.type = ? AND subscriptions.identificator = ?");
    $req->execute([$type, $id]);
} else {
    $req = $db->prepare("SELECT * FROM subscriptions GROUP BY `token`, `endpoint`, `key`, `auth`");
    $req->execute();
}

while ($f_row = $req->fetch()) {
    Token::$is = $f_row["token"];
    Push::reply(
        Push::sub(
            $f_row["endpoint"],
            $f_row["key"],
            $f_row["auth"],
        ),
        $raw,
        false,
        [],
        $f_row["id"],
    );
}

echo json(["result" => true]);
