<?php
$__push_mode = true;
require __DIR__ . "/../.private/scripts/index.php";

Token::$is = checkToken(true);

$endpoint = $_POST["endpoint"] ?? null;
$key = $_POST["key"] ?? null;
$auth = $_POST["auth"] ?? null;
$cert = $_POST["cert"] ?? null;
$type = $_POST["type"] ?? null;
$id = $_POST["id"] ?? null;
$expires = $_POST["expires"] ?? null;
$encoding = $_POST["encoding"] ?? null;

if ($cert !== Settings::$get->push->public) die(json(["error" => "Incorrect key"]));
$db = DB::get();

$subscription = Push::sub(
    $endpoint,
    $key,
    $auth,
);

if ($type !== "statement" && !($type === "sominemo" && $id === "app_updates")) {
    die(json(["error" => "Unknown subscription type"]));
}

$re = $db->prepare("SELECT * from `subscriptions` WHERE `type` = ? AND `identificator` = ? AND `token` = ? AND `endpoint` = ?");
$re->execute([$type, $id, Token::$is, $endpoint]);
$res = $re->fetch();


if ($res && $res["id"] > 0) {
    $re = $db->prepare("UPDATE `subscriptions` SET `date` = ?, `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ? WHERE `id` = ?");
    $re->execute([time(), $endpoint, $key, $auth, $expires, $encoding, $res["id"]]);
    
} else if ($type === "statement") {
    $client_raw = mono_sender("/personal/client-info", "GET", Token::$request);
    $client_data = is_string($client_raw) ? json_decode($client_raw) : null;
    if (
        json_last_error() !== JSON_ERROR_NONE
        || !is_object($client_data)
        || !isset($client_data->accounts)
        || !is_array($client_data->accounts)
        || count(array_filter($client_data->accounts, function ($element) {
            global $id;
            return $element->id === $id;
        })) <= 0
    ) {
        exit(json(["error" => "Unknown account"]));
    }

    $re = $db->prepare("INSERT into `subscriptions` SET `type` = ?, `identificator` = ?, `token` = ?, `date` = ?, `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ?");
    $r = $re->execute([$type, $id, Token::$is, time(), $endpoint, $key, $auth, $expires, $encoding]);
    if (!$r) die(json(["error" => "Request failed"]));
} else if ($type === "sominemo" && $id === "app_updates") {
    $re = $db->prepare("INSERT into `subscriptions` SET `type` = ?, `identificator` = ?, `token` = ?, `date` = ?, `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ?");
    $r = $re->execute([$type, $id, Token::$is, time(), $endpoint, $key, $auth, $expires, $encoding]);
    if (!$r) die(json(["error" => "Request failed"]));

    $push_id = $db->lastInsertId();
    $res = Push::reply(
        $subscription,
        json([
            "act" => "custom-push",
            "isMultilang" => true,
            "push" => [
                "uk" => [
                    "title" => "Новини",
                    "body" => "Ви успішно підписались на новини",
                    "libraryBadge" => "news",
                ],
                "ru" => [
                    "title" => "Новости",
                    "body" => "Вы успешно подписались на новости",
                    "libraryBadge" => "news",
                    "actionDescriptor" => [
                        "" => []
                    ]
                ],
                "en" => [
                    "title" => "News",
                    "body" => "You are now subscribed for Mono PWA news",
                    "libraryBadge" => "news",
                    "actionDescriptor" => [
                        "" => []
                    ]
                ]
            ]
        ]),
        false,
        ["TTL" => 0],
        $push_id,
    );

    if (!$res) die(json(["error" => "Subscription failed"]));
}

$re = $db->prepare("UPDATE `subscriptions` SET `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ? WHERE `token` = ? AND `endpoint` = ?");
$r = $re->execute([$key, $auth, $expires, $encoding, Token::$is, $endpoint]);

die(json(["result" => true]));
