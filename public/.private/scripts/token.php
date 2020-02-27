<?php

class Token
{
    static $is = "";
    static $request = null;
}

function checkToken($use_get = false)
{
    $token = null;
    if (!$use_get) {
        $headers = getallheaders();

        $headers["x-request-id"] = $headers["x-request-id"] ?: $headers["X-Request-Id"];
        if (isset($headers["x-request-id"])) $token = $headers["x-request-id"];
    } else {
        $token = $_GET["token"] ?: null;
    }

    if ($token !== null) {
        $db = DB::get();
        $st = $db->prepare("SELECT * from `tokens` WHERE `token` = ?");
        $st->execute([$token]);
        $r = $st->fetch();

        if (($r["id"] > 0)) {
            Token::$is = $token;
            Token::$request = $r["mono"];

            return $token;
        }
    }
    exit(json(["error" => "Invalid Mono-associated token"]));
}
