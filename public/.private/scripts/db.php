<?php

require(__DIR__."/../meta/dbdata.php");

class DB extends DBData
{

    private static $connection = null;

    public static function values($a)
    {
        // Checking what we've got
        if (!is_array($a)) {
            return '';
        }

        // Prepeared array
        $s = [];

        foreach ($a as $key => $value) {
            // Recoding
            $s[] = "`$key` = :$key";
        }
        // Connecting
        $s = implode(', ', $s);
        return $s;
    }

    static function reload()
    {
        try {
            $pdo = new PDO(
                "mysql:host=" . self::$domain . ";dbname=" . self::$db,
                self::$user,
                self::$password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_LAZY,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            self::$connection = $pdo;
        } catch (PDOException $e) {
            exit(json(["error" => "Failed to contact DB"]));
        }
    }

    static function get()
    {
        if (self::$connection === null) self::reload();
        return self::$connection;
    }
}
