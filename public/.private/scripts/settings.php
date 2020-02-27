<?php

class Settings {
    static $get = null;
}

Settings::$get = json_decode(file_get_contents(__DIR__."/../meta/settings.json"));
if (json_last_error() !== JSON_ERROR_NONE) die(json(["error" => "Settings reading error"]));