<?php
function json($data) {
    $j = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (debug) {
        try {
            $dat = $j.PHP_EOL;
            $fp = fopen(dirname(__FILE__)."/../meta/log.txt", 'a');
            fwrite($fp, $dat);
        } catch (Error $e) {
            
        }
    }
    return $j;
}