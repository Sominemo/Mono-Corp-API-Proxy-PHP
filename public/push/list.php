<?php
require __DIR__ . "/../.private/scripts/index.php";

checkToken(true);
$endpoint = $_POST["endpoint"];

$client_data = json_decode(mono_sender("/personal/client-info", "GET", Token::$request));

$statement_channels = [];
$additional_channels = [];

$additional_channels[] = [
    "type" => "sominemo",
    "id" => "app_updates",
    "icon" => "new_releases",
    "sign" => [
        "mode" => "local",
        "value" => "@push/i/news/sign",
    ],
    "description" => [
        "mode" => "local",
        "value" => "@push/i/news/description",
    ],
];

foreach ($client_data->accounts as $account) {
    $statement_channels[] = [
        "type" => "statement",
        "id" => $account->id,
        "icon" => "credit_card",
        "sign" => [
            "mode" => "statement",
            "value" => $account->id,
        ],
        "description" => [
            "mode" => "local",
            "value" => "@push/i/statement/description",
        ],
    ];
}

$channels_prepare = array_merge($additional_channels, $statement_channels);
$channels = [];

foreach ($channels_prepare as $channel) {
    $db = DB::get();
    $st = $db->prepare("SELECT * from `subscriptions` WHERE `type` = ? AND `identificator` = ? AND `token` = ? AND `endpoint` = ?");
    $st->execute([$channel["type"], $channel["id"], Token::$is, $endpoint]);
    $r = $st->fetch();
    $channel["state"] = ($r["state"] === 1 && ($r["expires"] > time() || $r["expires"] === 0) ? true : false);
    $channels[] = $channel;
}

die(json($channels));
