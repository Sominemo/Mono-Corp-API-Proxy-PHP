<?php

if (!defined('debug')) {
    define('debug', false);
}

function pl(array $v, array $a): bool
{
    return array_all($a, static fn(string $key): bool => isset($v[$key]) && is_string($v[$key]));
}

require_once __DIR__ . "/.private/scripts/json.php";

if (pl($_POST, [
    "db", "dbuser", "dbdomain", "dbpass",
    "privatekey", "keyid", "apidomain", "headerbl"
])) {

    class DeleteOnExit
    {
        public static bool $done = false;

        public function __destruct()
        {
            if (self::$done && is_file(__FILE__)) {
                unlink(__FILE__);
            }
        }
    }

    $g_delete_on_exit = new DeleteOnExit();

    $db_name = var_export($_POST["db"], true);
    $db_user = var_export($_POST["dbuser"], true);
    $db_domain = var_export($_POST["dbdomain"], true);
    $db_pass = var_export($_POST["dbpass"], true);

    $meta_dir = __DIR__ . "/.private/meta";
    if (!is_dir($meta_dir)) {
        mkdir($meta_dir, 0755, true);
    }

    file_put_contents(
        "$meta_dir/dbdata.php",
        "<?php

class DBData
{
    protected static \$db = $db_name;
    protected static \$user = $db_user;
    protected static \$domain = $db_domain;
    protected static \$password = $db_pass;
}
"
    );

    require_once __DIR__ . "/.private/scripts/db.php";
    $db = DB::get();

    $db->exec("
CREATE TABLE IF NOT EXISTS `clients_count` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `time` int(11) NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `roll-in` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` char(45) COLLATE utf8_unicode_ci NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mono_token` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `proof` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `mono` tinytext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` char(255) COLLATE cp1251_ukrainian_ci NOT NULL,
  `identificator` char(255) COLLATE cp1251_ukrainian_ci NOT NULL,
  `token` char(255) COLLATE cp1251_ukrainian_ci NOT NULL,
  `date` int(11) NOT NULL DEFAULT '0',
  `state` int(11) NOT NULL DEFAULT '1',
  `endpoint` text COLLATE cp1251_ukrainian_ci NOT NULL,
  `key` text COLLATE cp1251_ukrainian_ci NOT NULL,
  `auth` text COLLATE cp1251_ukrainian_ci NOT NULL,
  `encoding` text COLLATE cp1251_ukrainian_ci NOT NULL,
  `expires` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=cp1251 COLLATE=cp1251_ukrainian_ci;

CREATE TABLE IF NOT EXISTS `tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `roll` tinytext COLLATE utf8_unicode_ci,
  `mono` tinytext COLLATE utf8_unicode_ci NOT NULL,
  `mono_id` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ");

    $private_key = openssl_pkey_get_private($_POST["privatekey"], "");
    if ($private_key === false) {
        http_response_code(400);
        header("Content-Type: text/html; charset=UTF-8");
        die("<pre>Invalid private key provided. Could not parse EC private key with OpenSSL.</pre>");
    }

    $key_details = openssl_pkey_get_details($private_key);
    if ($key_details === false || !isset($key_details["key"])) {
        http_response_code(400);
        header("Content-Type: text/html; charset=UTF-8");
        die("<pre>Failed to extract public key from the provided private key.</pre>");
    }
    $public_key = $key_details["key"];

    file_put_contents("$meta_dir/priv.key", $_POST["privatekey"]);
    file_put_contents("$meta_dir/pub.key", $public_key);
    file_put_contents("$meta_dir/key-id", $_POST["keyid"]);

    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $protocol = $is_https ? 'https://' : 'http://';
    $server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $script_dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
    $base_url = $protocol . $server_name . $script_dir;

    $path = $base_url;
    $api_domain = $_POST["apidomain"] !== "" ? $_POST["apidomain"] : "https://api.monobank.ua";
    $header_bl = array_values(array_filter(explode("|", $_POST["headerbl"])));
    if ($header_bl === []) {
        $header_bl = ["SSL", "GeoIp-Country-Code", "Host"];
    }

    $install_log = [];
    $composer_install_output = [];
    $composer_require_output = [];
    $public_push_cert = false;
    $private_push_cert = false;
    $pushproof = false;
    $push_works = false;

    $no_push_server = ($_POST["nopushserver"] ?? null) === "1";
    $composer_deps = ($_POST["composerdeps"] ?? null) === "1";
    $dl_composer = ($_POST["dlcomposer"] ?? null) === "1";
    $set_webhook = ($_POST["webhook"] ?? null) === "1";

    if (!$no_push_server) {
        if ($composer_deps) {
            $project_root = dirname(__DIR__);
            $php_executable = trim((string) ($_POST["phpexecutable"] ?? ""));
            if ($php_executable === "") {
                $php_executable = "php";
            }

            $escaped_root = escapeshellarg($project_root);
            $escaped_php = escapeshellarg($php_executable);

            if ($dl_composer) {
                $installer = file_get_contents("https://getcomposer.org/installer");
                if ($installer === false) {
                    $composer_install_output[] = "Failed to download Composer installer from https://getcomposer.org/installer";
                } else {
                    $composer_setup = "$project_root/composer-setup.php";
                    file_put_contents($composer_setup, $installer);
                    exec("export HOME=$escaped_root && cd $escaped_root && $escaped_php composer-setup.php 2>&1", $composer_install_output);
                    if (is_file($composer_setup)) {
                        unlink($composer_setup);
                    }
                }
            }

            $composer_cmd = is_file("$project_root/composer.phar")
                ? "$escaped_php composer.phar install 2>&1"
                : "composer install 2>&1";
            exec("export HOME=$escaped_root && cd $escaped_root && $composer_cmd", $composer_require_output);

            $install_log = array_merge($install_log, $composer_install_output, $composer_require_output);
        }

        try {
            $autoload_path = __DIR__ . '/../vendor/autoload.php';
            if (!is_file($autoload_path)) {
                throw new RuntimeException("Vendor autoload file not found");
            }
            require_once $autoload_path;
            if (!class_exists(Minishlink\WebPush\WebPush::class)) {
                throw new RuntimeException("No WebPush library");
            }
            $push_works = true;
        } catch (Throwable $e) {
            header("Content-Type: text/html; charset=UTF-8");
            echo "<pre>";
            echo "Web Push libraries are not installed.\nDisable Push Server at setup page if you are not going to support this feature.\n\n";
            foreach ($install_log as $value) {
                echo htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
            }
            die("</pre>");
        }

        $webhook_secret = (string) ($_POST["webhooksecret"] ?? "");
        $pushproof = $webhook_secret === "" ? bin2hex(random_bytes(10)) : $webhook_secret;

        $public_push_input = (string) ($_POST["publicpushcert"] ?? "");
        $private_push_input = (string) ($_POST["privatepushcert"] ?? "");
        $public_push_cert = $public_push_input === "" ? false : $public_push_input;
        $private_push_cert = $private_push_input === "" ? false : $private_push_input;

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
        "$meta_dir/settings.json",
        json($settings)
    );

    if (!$no_push_server && $set_webhook && $push_works) {
        require_once __DIR__ . "/.private/scripts/settings.php";
        require_once __DIR__ . "/.private/scripts/mono-sender.php";

        $api_log = [];
        $auth = mono_sender(
            "/personal/corp/webhook",
            "POST",
            null,
            json(
                [
                    "webHookUrl" => $base_url . "/push/" . Settings::$get->push->proof . "/accept",
                ]
            ),
            [],
            "",
            $api_log
        );
        $install_log = array_merge($install_log, $api_log);

        $api_log = [];
        mono_sender(
            "/personal/corp/settings",
            "GET",
            null,
            "{}",
            [],
            "",
            $api_log
        );
        $install_log = array_merge($install_log, $api_log);
    }

    DeleteOnExit::$done = true;
    header("Content-Type: text/html; charset=UTF-8");
    echo '
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
    <details><summary><b>Logs</b></summary><pre>';
    foreach ($install_log as $value) {
        echo htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
    }
    echo '</pre></details></body>
</html>
';
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
    <form action="?" method="POST">
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
            <label for="keyid">
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
            </p>
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
    $ composer require minishlink/web-push:^11.0 guzzlehttp/guzzle:^7.9
</pre>
                <p>
                    If you can't use Composer,
                    download the package from <a href="https://github.com/Sominemo/Mono-Corp-API-Proxy-PHP/releases" target="_blank">GitHub Releases</a>
                    or <a href="https://php-download.com/" target="_blank">PHP Download</a>
                    and unpack it to the parent directory of <b>public</b>, so <b>public</b> and <b>vendor</b> folders will be near.
                </p>
            </details>
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
                <label for="privatepushcert">
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
            <legend>Write &amp; Set Up</legend>
            <input type="submit">
        </fieldset>
    </form>
</body>

</html>