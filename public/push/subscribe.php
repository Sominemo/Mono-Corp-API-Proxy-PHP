<?php
$__push_mode = true;
require __DIR__ . "/../.private/scripts/index.php";

Token::$is = checkToken(true);

$endpoint = $_POST["endpoint"];
$key = $_POST["key"];
$auth = $_POST["auth"];
$cert = $_POST["cert"];
$type = $_POST["type"];
$id = $_POST["id"];
$expires = $_POST["expires"];
$encoding = $_POST["encoding"];

if ($cert !== Settings::$get->push->public) die(json(["error" => "Incorrect key"]));
$db = DB::get();

$subscription = Push::sub(
    $endpoint,
    $key,
    $auth,
);

$re = $db->prepare("SELECT * from `subscriptions` WHERE `type` = ? AND `identificator` = ? AND `token` = ?");
$re->execute([$type, $id, Token::$is]);
$res = $re->fetch();


if ($res["id"] > 0) {
    $re = $db->prepare("UPDATE `subscriptions` SET `date` = ?, `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ? WHERE `type` = ? AND `identificator` = ? AND `token` = ?");
    $re->execute([time(), $endpoint, $key, $auth, $expires, $encoding, $type, $id, Token::$is]);
    
} else if ($type === "statement") {
    $client_data = json_decode(mono_sender("/personal/client-info", "GET", Token::$request));
    if (count(array_filter($client_data->accounts, function ($element) {
        global $id;
        return $element->id === $id;
    })) <= 0) {
        exit(json(["error" => "Unknown account"]));
    }

    $re = $db->prepare("INSERT into `subscriptions` SET `type` = ?, `identificator` = ?, `token` = ?, `date` = ?, `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ?");
    $r = $re->execute([$type, $id, Token::$is, time(), $endpoint, $key, $auth, $expires, $encoding]);
    if (!$r) die(json(["error" => "Request failed"]));
} else if ($type === "sominemo" && $id == "app_updates") {
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
                ]
            ]
        ]),
        false,
        ["TTL" => 0],
        $push_id,
    );

    if (!$res) die(json(["error" => "Subscription failed"]));
}

$re = $db->prepare("UPDATE `subscriptions` SET `endpoint` = ?, `key` = ?, `auth` = ?, `expires` = ?, `encoding` = ? WHERE `token` = ?");
$r = $re->execute([$endpoint, $key, $auth, $expires, $encoding, Token::$is]);

die(json(["result" => true]));
