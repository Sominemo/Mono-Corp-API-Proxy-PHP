<?php
$exe_start = time();
require __DIR__."/.private/scripts/index.php";

function success($token)
{
    die(json(["token" => $token]));
}

$roll = (isset($_GET["token"]) ? $_GET["token"] : $_POST["token"]);
$db = DB::get();

$st = $db->prepare("SELECT COUNT(*) from `roll-in` WHERE `token` = :token AND `time` > NOW() - INTERVAL 15 minute");
$st->execute([
    "token" => $roll
]);
$cnt = $st->fetchColumn();
if (!($cnt > 0)) {
    $st = $db->prepare("SELECT `id`, `token` from `tokens` WHERE `roll` = :roll");
    $st->execute([
        "roll" => $roll
    ]);
    $cnt = $st->fetch();

    if ($cnt["id"] > 0) {
        $st = $db->prepare("UPDATE `tokens` SET `roll` = NULL WHERE `id` = ?");
        $st->execute([$cnt["id"]]);

        success($cnt["token"]);
    }

    die(json(["error" => "Unknown roll token"]));
}

function left()
{
    global $exe_start;
    $passed = time() - $exe_start;
    return 25 - $passed;
}


while (left() > 1) {
    sleep(1);
    $st = $db->prepare("SELECT `id`, `token` from `tokens` WHERE `roll` = :roll");
    $st->execute([
        "roll" => $roll
    ]);
    $cnt = $st->fetch();

    if ($cnt["id"] > 0) {
        $st = $db->prepare("UPDATE `tokens` SET `roll` = NULL WHERE `id` = ?");
        $st->execute([$cnt["id"]]);

        success($cnt["token"]);
    }
}

die(json(["token" => false]));
