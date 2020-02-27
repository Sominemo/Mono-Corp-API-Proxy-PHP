<?php

function pl($v, $a)
{
    foreach ($a as $value) {
        if (!isset($v[$value])) return false;
    }

    return true;
}

function json($data)
{
    $j = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (debug) {
        try {
            $dat = $j . PHP_EOL;
            $fp = fopen(dirname(__FILE__) . "/../meta/log.txt", 'a');
            fwrite($fp, $dat);
        } catch (Error $e) {
        }
    }
    return $j;
}

if (pl($_POST, [
    "db", "dbuser", "dbdomain", "dbpass",
    "privatekey", "keyid", "apidomain", "headerbl"
])) {

    class DeleteOnExit
    {
        function __destruct()
        {
            unlink(__FILE__);
        }
    }

    $g_delete_on_exit = new DeleteOnExit();

    $db = json($_POST["db"]);
    $dbuser = json($_POST["dbuser"]);
    $dbdomain = json($_POST["dbdomain"]);
    $dbpass = json($_POST["dbpass"]);

    if (!is_dir(__DIR__ . "/.private/meta/")) {
        mkdir(__DIR__ . "/.private/meta/");
    }

    file_put_contents(
        __DIR__ . "/.private/meta/dbdata.php",
        "<?php

class DBData
{
    protected static \$db = $db;
    protected static \$user = $dbuser;
    protected static \$domain = $dbdomain;
    protected static \$password = $dbpass;
}
"
    );

    require(__DIR__ . "/.private/scripts/db.php");
    $db = DB::get();

    $db->exec("
    CREATE TABLE IF NOT EXISTS `roll-in` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `token` CHAR(45) NOT NULL COLLATE 'utf8_unicode_ci',
        `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `mono_token` TINYTEXT NOT NULL COLLATE 'utf8_unicode_ci',
        `proof` TINYTEXT NOT NULL COLLATE 'utf8_unicode_ci',
        `mono` TINYTEXT NOT NULL COLLATE 'utf8_unicode_ci',
        PRIMARY KEY (`id`),
        UNIQUE INDEX `token` (`token`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=MyISAM
    AUTO_INCREMENT=152
    ;
    CREATE TABLE IF NOT EXISTS `tokens` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `token` VARCHAR(50) NOT NULL COLLATE 'utf8_unicode_ci',
        `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `roll` TINYTEXT NULL COLLATE 'utf8_unicode_ci',
        `mono` TINYTEXT NOT NULL COLLATE 'utf8_unicode_ci',
        PRIMARY KEY (`id`),
        UNIQUE INDEX `token` (`token`)
    )
    COLLATE='utf8_unicode_ci'
    ENGINE=MyISAM
    AUTO_INCREMENT=55
    ;
    ");

    file_put_contents(__DIR__ . "/.private/meta/priv.key", $_POST["privatekey"]);

    $private_resource = openssl_get_privatekey($_POST["privatekey"], "");
    $public_key = openssl_pkey_get_details($private_resource)['key'];

    file_put_contents(__DIR__ . "/.private/meta/pub.key", $public_key);
    file_put_contents(__DIR__ . "/.private/meta/key-id", $_POST["keyid"]);

    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        $protocol = 'http://';
    } else {
        $protocol = 'https://';
    }
    $base_url = $protocol . $_SERVER['SERVER_NAME'] . substr(dirname($_SERVER['PHP_SELF']), 0, -1);

    $path = $base_url;
    $api_domain = (empty($_POST["apidomain"]) ? "https://api.monobank.ua" : $_POST["apidomain"]);
    $header_bl = array_filter(explode("|", $_POST["headerbl"]));
    if (count($header_bl) === 0) $header_bl = ["SSL", "GeoIp-Country-Code", "Host"];

    $public_push_cert = (empty($_POST["publicpushcert"]) ? false : $_POST["publicpushcert"]);
    $private_push_cert = (empty($_POST["privatepushcert"]) ? false : $_POST["privatepushcert"]);

    file_put_contents(
        __DIR__ . "/.private/meta/settings.json",
        json(["path" => $path, "api_domain" => $api_domain, "headers_blacklist" => $header_bl, "push" => ["public" => $public_push_cert, "private" => $private_push_cert]])
    );
    exit('
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Success</title>
</head>
<body>
    <h1 style="margin-bottom: 0">Success</h1>
    <p style="margin-top: 0">Installation script saved the configuration <b>and self-destructed</b></p>
</body>
</html>    
');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Setup</title>
</head>

<body>
    <h1>Mono Corporate API Proxy Setup</h1>
    <hr>
    <form action="?" , method="POST">
        <fieldset>
            <legend>Database</legend>

            <input type="text" name="dbdomain" placeholder="Domain"><br>
            <input type="text" name="db" placeholder="DB"><br>
            <input type="text" name="dbuser" placeholder="User"><br>
            <input type="text" name="dbpass" placeholder="Password">
        </fieldset>
        <fieldset>
            <legend>Signing</legend>

            Private key: <br><textarea name="privatekey" rows="10" cols="70" placeholder="-----BEGIN EC PARAMETERS-----"></textarea><br>
            <input type="text" name="keyid" size="70" placeholder="Key ID">
        </fieldset>
        <fieldset>
            <legend>Monobank API</legend>
            <input type="url" name="apidomain" placeholder="https://api.monobank.ua"><br>
            Blacklisted headers: <br><input type="text" name="headerbl" size="70" placeholder="SSL|GeoIp-Country-Code|Host">
        </fieldset>
        <fieldset>
            <legend>Push API</legend>
            <pre>
    $ npm install -g web-push
    $ web-push generate-vapid-keys
            </pre>
            <input type="text" name="publicpushcert" placeholder="Public Push Cert" size="90"><br>
            <input type="text" name="privatepushcert" placeholder="Private Push Cert" size="90"><br>
        </fieldset>
        <fieldset>
            <legend>Write & Set Up</legend>
            <input type="submit">
        </fieldset>
    </form>
</body>

</html>