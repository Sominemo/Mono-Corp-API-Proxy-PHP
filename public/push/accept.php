<?php
// file_put_contents("log.txt", "\nSTART", FILE_APPEND);
$raw = file_get_contents('php://input');
$input = json_decode($raw);
// file_put_contents("log.txt", "\n$raw", FILE_APPEND);

$__force_debug = true;
require __DIR__ . "/../.private/scripts/index.php";

if ($_GET["token"] !== Settings::$get->push->proof) {
    http_response_code(403);
    exit(json(["error" => "Incorrect server proof"]));
}


if ($input->type === "StatementItem") {
    $db = DB::get();
    $req = $db->prepare("SELECT subscriptions.*, tokens.mono FROM subscriptions, tokens WHERE subscriptions.type = ? AND subscriptions.identificator = ? AND subscriptions.token = tokens.token");
    $req->execute(["statement", $input->data->account]);
    $account = null;
    while ($f_row = $req->fetch()) {

        if (!$account) {
            $client = json_decode(mono_sender("/personal/client-info", "GET", $f_row["mono"]));
            if (gettype($client->accounts) !== "array") $account = ["id" => $input->data->account];
            else {
                $account = array_values(array_filter($client->accounts, function ($element) {
                    global $input;
                    return $element->id === $input->data->account;
                }));
                $account = count($account) > 0 ? $account[0] : ["id" => $input->data->account];
            }
        }

        Token::$is = $f_row["token"];
        Push::reply(
            Push::sub(
                $f_row["endpoint"],
                $f_row["key"],
                $f_row["auth"],
            ),
            json(
                [
                    "act" => "statement-item",
                    "account" => $account,
                    "item" => $input->data->statementItem,
                ]
            ),
            false,
            [],
            $f_row["id"],
        );
    }
}
echo json(["result" => true]);