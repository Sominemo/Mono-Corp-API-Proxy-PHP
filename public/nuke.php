<?php
require __DIR__."/.private/scripts/index.php";

$current_token = checkToken(false);

$db = DB::get();

$mono_id_req = $db->prepare("SELECT `mono_id`, `mono`, `roll` FROM `tokens` WHERE `token` = :token");
$success = $mono_id_req->execute(["token" => $current_token]);
if (!$success) die(json(["error" => "Failed to find user mono_id"]));
$current_row = $mono_id_req->fetch(PDO::FETCH_ASSOC);
$mono_id = $current_row ? $current_row["mono_id"] : null;
$current_mono = $current_row ? $current_row["mono"] : Token::$request;

$account_ids = [];
$client_raw = mono_sender("/personal/client-info", "GET", $current_mono);
$client_data = is_string($client_raw) ? json_decode($client_raw) : null;
if (json_last_error() === JSON_ERROR_NONE && is_object($client_data)) {
    if (empty($mono_id) && isset($client_data->clientId) && is_string($client_data->clientId) && strlen($client_data->clientId) > 0) {
        $mono_id = $client_data->clientId;
    }
    if (isset($client_data->accounts) && is_array($client_data->accounts)) {
        foreach ($client_data->accounts as $account) {
            if (is_object($account) && isset($account->id) && is_string($account->id) && strlen($account->id) > 0) {
                $account_ids[] = $account->id;
            }
        }
        $account_ids = array_values(array_unique($account_ids));
    }
}

$token_where = ["`token` = :token"];
$token_params = ["token" => $current_token];
if (!empty($mono_id)) {
    $token_where[] = "`mono_id` = :mono_id";
    $token_params["mono_id"] = $mono_id;
}
if (!empty($current_mono)) {
    $token_where[] = "`mono` = :mono";
    $token_params["mono"] = $current_mono;
}
$token_where_sql = implode(" OR ", $token_where);

$tokens_req = $db->prepare("SELECT `token`, `mono`, `roll` FROM `tokens` WHERE $token_where_sql");
$success = $tokens_req->execute($token_params);
if (!$success) die(json(["error" => "Failed to find user tokens"]));

$tokens = $tokens_req->fetchAll(PDO::FETCH_ASSOC);

$token_values = [];
$mono_values = [];
$roll_values = [];
foreach ($tokens as $row) {
    if (!empty($row["token"])) $token_values[] = $row["token"];
    if (!empty($row["mono"])) $mono_values[] = $row["mono"];
    if (!empty($row["roll"])) $roll_values[] = $row["roll"];
}
$token_values = array_values(array_unique($token_values));
$mono_values = array_values(array_unique($mono_values));
$roll_values = array_values(array_unique($roll_values));

try {
    $db->beginTransaction();

    if (count($token_values) > 0) {
        $placeholders = implode(", ", array_fill(0, count($token_values), "?"));
        $delete_sub_req = $db->prepare("DELETE FROM `subscriptions` WHERE `token` IN ($placeholders)");
        if (!$delete_sub_req->execute($token_values)) {
            $db->rollBack();
            die(json(["error" => "Failed to delete subscriptions"]));
        }
    }

    if (count($account_ids) > 0) {
        $placeholders = implode(", ", array_fill(0, count($account_ids), "?"));
        $delete_account_sub_req = $db->prepare("DELETE FROM `subscriptions` WHERE `type` = 'statement' AND `identificator` IN ($placeholders)");
        if (!$delete_account_sub_req->execute($account_ids)) {
            $db->rollBack();
            die(json(["error" => "Failed to delete account subscriptions"]));
        }
    }

    if (count($mono_values) > 0) {
        $placeholders = implode(", ", array_fill(0, count($mono_values), "?"));
        $delete_rollin_mono_req = $db->prepare("DELETE FROM `roll-in` WHERE `mono_token` IN ($placeholders)");
        if (!$delete_rollin_mono_req->execute($mono_values)) {
            $db->rollBack();
            die(json(["error" => "Failed to delete roll-in data"]));
        }
    }

    if (count($roll_values) > 0) {
        $placeholders = implode(", ", array_fill(0, count($roll_values), "?"));
        $delete_rollin_token_req = $db->prepare("DELETE FROM `roll-in` WHERE `token` IN ($placeholders)");
        if (!$delete_rollin_token_req->execute($roll_values)) {
            $db->rollBack();
            die(json(["error" => "Failed to delete roll-in data"]));
        }
    }

    $delete_tokens_req = $db->prepare("DELETE FROM `tokens` WHERE $token_where_sql");
    if (!$delete_tokens_req->execute($token_params)) {
        $db->rollBack();
        die(json(["error" => "Failed to delete tokens"]));
    }

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}

$result = [
    "status" => true,
];

die(json($result));
