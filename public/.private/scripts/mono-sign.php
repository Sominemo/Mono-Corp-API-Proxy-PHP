<?php

function mono_sign($url, $t, $time) {
    $key = openssl_get_privatekey("file://".dirname(__FILE__)."/../meta/priv.key", "");
    if (!$key) die(json(["error" => "Signing error"]));
    $str = $time.$t.$url;
    openssl_sign($str, $sig, $key, OPENSSL_ALGO_SHA256);
    openssl_free_key($key);
    return base64_encode($sig);
}

