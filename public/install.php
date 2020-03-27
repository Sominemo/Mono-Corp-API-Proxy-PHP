<?php
function pl($v, $a)
{
    foreach ($a as $value) {
        if (!isset($v[$value])) return false;
    }

    return true;
}

require_once "./.private/scripts/json.php";

if (pl($_POST, [
    "db", "dbuser", "dbdomain", "dbpass",
    "privatekey", "keyid", "apidomain", "headerbl"
])) {

    class DeleteOnExit
    {
        static $done = false;

        function __destruct()
        {
            if (self::$done) unlink(__FILE__);
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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` char(45) COLLATE utf8_unicode_ci NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mono_token` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `proof` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `mono` tinytext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=MyISAM AUTO_INCREMENT=853 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` char(255) COLLATE utf8_unicode_ci NOT NULL,
  `identificator` char(255) COLLATE utf8_unicode_ci NOT NULL,
  `token` char(255) COLLATE utf8_unicode_ci NOT NULL,
  `date` int(11) NOT NULL DEFAULT '0',
  `state` int(11) NOT NULL DEFAULT '1',
  `endpoint` text COLLATE utf8_unicode_ci NOT NULL,
  `key` text COLLATE utf8_unicode_ci NOT NULL,
  `auth` text COLLATE utf8_unicode_ci NOT NULL,
  `encoding` text COLLATE utf8_unicode_ci NOT NULL,
  `expires` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=241 DEFAULT CHARSET=cp1251 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `roll` tinytext COLLATE utf8_unicode_ci,
  `mono` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `mono_id` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=MyISAM AUTO_INCREMENT=491 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
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

    $install_log = [];
    $composer_install_output = [];
    $composer_require_output = [];
    $public_push_cert = false;
    $private_push_cert = false;
    $pushproof = false;

    if (!($_POST["nopushserver"] === "1")) {
        if ($_POST["composerdeps"] === "1") {
            $DIR = __DIR__;
            $php_executable = (empty($_POST["phpexecutable"]) ? "php" : $_POST["phpexecutable"]);

            if ($_POST["dlcomposer"] === "1") {
                file_put_contents("../composer-setup.php", file_get_contents("https://getcomposer.org/installer"));
                exec("export HOME=$DIR/.. && cd .. && $php_executable composer-setup.php 2>&1", $composer_install_output);
            }

            exec("export HOME=$DIR/.. && cd .. && $php_executable composer.phar install 2>&1", $composer_require_output);

            $install_log = array_merge($install_log, $composer_install_output, $composer_require_output);
        }

        try {
            require __DIR__ . '/../vendor/autoload.php';
            if (!class_exists("Minishlink\WebPush\WebPush")) throw new Exception("No WebPush library");
            $push_works = true;
        } catch (Exception $e) {
            echo "<pre>";
            echo ("Web Push libraries are not installed. \nDisable Push Server at setup page if you are not going to support this feature.\n\n");
            foreach ($install_log as $value) {
                print($value . "\n");
            }
            die("</pre>");
        }

        $pushproof = (empty($_POST["webhooksecret"]) ? bin2hex(random_bytes(10)) : $_POST["webhooksecret"]);

        $public_push_cert = (empty($_POST["publicpushcert"]) ? false : $_POST["publicpushcert"]);
        $private_push_cert = (empty($_POST["privatepushcert"]) ? false : $_POST["privatepushcert"]);

        if (!$public_push_cert && !$private_push_cert) {
            $key_gen = Minishlink\WebPush\VAPID::createVapidKeys();

            $public_push_cert = $key_gen["publicKey"];
            $private_push_cert = $key_gen["privateKey"];
        }
    }

    $settings = [
        "path" => $path,
        "api_domain" => $api_domain,
        "headers_blacklist" => $header_bl,
        "push" => [
            "public" => $public_push_cert,
            "private" => $private_push_cert,
            "proof" => $pushproof,
        ]
    ];

    file_put_contents(
        __DIR__ . "/.private/meta/settings.json",
        json($settings)
    );


    if (!($_POST["nopushserver"] === "1") && $_POST["webhook"] === "1" && $push_works) {
        require_once __DIR__ . "/.private/scripts/index.php";

        $auth = mono_sender(
            "/personal/corp/webhook",
            "POST",
            null,
            json(
                [
                    "webHookUrl" => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['SERVER_NAME']}/push/" . Settings::$get->push->proof . "/accept",
                ]
            ),
            [],
            "",
            $api_log
        );
        $install_log = array_merge($install_log, $api_log);

        mono_sender(
            "/personal/corp/settings",
            "GET",
            null,
            json([]),
            [],
            "",
            $api_log
        );
        $install_log = array_merge($install_log, $api_log);
    }

    DeleteOnExit::$done = true;
    header("Content-Type: text/html");
    echo ('
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" id="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Success</title>
</head>
<body>
    <h1 style="margin-bottom: 0">Success</h1>
    <p style="margin-top: 0">Installation script saved the configuration <b>and self-destructed</b></p>
    <details><summary><b>Logs</b></summary><pre>');
    foreach ($install_log as $value) {
        echo $value . "\n";
    }
    echo ('</pre></details></body>
</html>    
');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" id="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Setup</title>
    <style>
        label:not(.has-checkbox) {
            display: inline-block;
            width: 10em;
            vertical-align: top;
            margin-top: 10px;
        }

        input[type="text"],
        textarea {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <h1>Mono Corporate API Proxy Setup</h1>
    <hr>
    <form action="?" , method="POST">
        <fieldset>
            <legend>Database</legend>

            <label for="dbdomain">
                Host:
            </label>
            <input type="text" name="dbdomain" id="dbdomain" placeholder="db.example.com"><br>

            <label for="db">
                Name:
            </label>
            <input type="text" name="db" id="db" placeholder="mono_database"><br>

            <label for="dbuser">
                User:
            </label>
            <input type="text" name="dbuser" id="dbuser" placeholder="admin"><br>

            <label for="dbpass">
                Password:
            </label>
            <input type="text" name="dbpass" id="dbpass" placeholder="********">
        </fieldset>
        <fieldset>
            <legend>Signing</legend>

            <label for="privatekey">
                Private key:
            </label>
            <textarea name="privatekey" id="privatekey" rows="10" cols="70" placeholder="-----BEGIN EC PARAMETERS-----"></textarea><br>
            <label for="privatekey">
                Key ID:
            </label>
            <input type="text" name="keyid" id="keyid" size="70" placeholder="~40 characters long hexadecimal number">
        </fieldset>
        <fieldset>
            <legend>Monobank API</legend>
            <label for="apidomain">
                API Endpoint:
            </label>
            <input type="url" name="apidomain" id="apidomain" placeholder="https://api.monobank.ua"><br>
            <label for="headerbl">
                Blacklisted headers:
            </label>
            <input type="text" name="headerbl" id="headerbl" size="70" placeholder="SSL|GeoIp-Country-Code|Host">
        </fieldset>
        <fieldset>
            <legend>Push Server</legend>
            <p>
                <label for="nopushserver" class="has-checkbox">
                    <input type="checkbox" name="nopushserver" id="nopushserver" value="1">
                    Disable Push Server and skip this block
                </label>
                <details>
                    <summary><b>Composer will be used to install Web Push libraries. Click to change that.</b></summary>
                    <label for="composerdeps" class="has-checkbox">
                        <input type="checkbox" name="composerdeps" id="composerdeps" value="1" checked>
                        Install Web Push libraries via Composer automatically
                    </label><br>
                    <label for="webhook" class="has-checkbox">
                        <input type="checkbox" name="webhook" id="webhook" value="1" checked>
                        Set webhook in Monobank API
                    </label><br>
                    <label for="dlcomposer" class="has-checkbox">
                        <input type="checkbox" name="dlcomposer" id="dlcomposer" value="1" checked>
                        Download Composer on the fly
                    </label><br>
                    <label for="phpexecutable">
                        PHP Executable Path:
                    </label>
                    <input type="text" name="phpexecutable" id="phpexecutable" placeholder="php">
                    <p>If you unchecked the first option, but still want Push Server to work, before proceeding use following command:</p>
                    <pre>
    $ composer install
</pre>
                    <p>to initiate the project from composer.lock, or install packages manually:</p>
                    <pre>
    $ composer require minishlink/web-push 5.2.4
</pre>
                    <p>
                        If you can't use Composer,
                        download the package from <a href="https://github.com/Sominemo/Mono-Corp-API-Proxy-PHP/releases" target="_blank">GitHub Releases</a>
                        or <a href="https://php-download.com/" target="_blank">PHP Download</a>
                        and unpack it to the parent directory of <b>public</b>, so <b>public</b> and <b>vendor</b> folders will be near.
                    </p>
                </details>
            </p>
            Your Push API keys will be generated automatically
            <details>
                <summary><b>Use my own</b></summary>
                <p>
                    You can generate your VAPID keys <a href="https://tools.reactpwa.com/vapid" target="_blank">online</a> or by these commands:
                </p>
                <pre>
    $ npm install -g web-push
    $ web-push generate-vapid-keys
</pre>

                <label for="publicpushcert">
                    Public Push Key:
                </label>
                <input type="text" name="publicpushcert" id="publicpushcert" placeholder="~87 characters" size="90"><br>
                <label for="publicpushcert">
                    Private Push Key:
                </label>
                <input type="text" name="privatepushcert" id="privatepushcert" placeholder="~43 characters" size="45"><br>
            </details>
            <p>
                Password for monobank to prevent strangers from sending fake events. If not filled, generates automatically.<br>
                <label for="webhooksecret">
                    Webhook secret:
                </label>
                <input type="text" name="webhooksecret" id="webhooksecret" placeholder="Generate automatically" size="45"><br>
            </p>
        </fieldset>
        <fieldset>
            <legend>Write & Set Up</legend>
            <input type="submit">
        </fieldset>
    </form>
</body>

</html>