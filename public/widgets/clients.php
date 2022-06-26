<?php

require __DIR__ . "/../.private/scripts/index.php";
$db = DB::get();
$s = $db->prepare("SELECT * from `clients_count` WHERE `time` > ? ORDER BY `time` DESC LIMIT 1");
$s->execute([time() - 5 * 60]);
$c = $s->fetch();
if (!($c["id"] > 0)) {
    $cnt = json_decode(file_get_contents("https://www.monobank.ua/api/dashboard/stat/clcnt"))->result->clientCnt;
    if ($cnt) {
        $s = $db->prepare("INSERT into `clients_count` SET `time` = ?, `value` = ?");
        $s->execute([time(), $cnt]);
    }
} else {
    $cnt = $c["value"];
}

$s = $db->prepare("SELECT MAX(`time`) as `time`, DAY(FROM_UNIXTIME(`time`)) as `day`, MAX(`value`) as `value` FROM `clients_count` WHERE `time` >= ? AND `time` < ? GROUP BY `day` ORDER BY `time` DESC LIMIT 7");

$date = new DateTime();
$date->setTime(0, 0, 0);
$s->execute([time() - 60*60*24*8, $date->getTimestamp()]);

$data = [];
while ($v = $s->fetch()) {
    $data[] =  $v["value"];
}

$data_r = array_reverse($data);
$data = [];

$prev = 0;
foreach ($data_r as $v) {
    $data[] = $v - $prev;
    $prev = $v;
}

$data = array_slice($data, 1);

echo json([
    "count" => $cnt,
    "history" => $data,
    "delta" => ($data[count($data) - 1] > $data[count($data) - 2] ? 1 : -1) * $data[count($data) - 1],
]);
