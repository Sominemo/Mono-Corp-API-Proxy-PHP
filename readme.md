# Mono-Corp-API-Proxy-PHP
Example implementation of [Mono Corp API Proxy Protocol](https://gist.github.com/Sominemo/64845669d6326f2f73d356f025656bdb)

## Requirements
- PHP 7.3+
- MySQL server
- OpenSSL extension enabled

## Install
1. Open folder, that contains the public folder of your PHP server
```shell
git clone https://github.com/Sominemo/Mono-Corp-API-Proxy-PHP.git .
rm -rf .git
rm .gitignore
```
3. Delete public folder of your PHP server
4. Rename *public* to the name, that your deleted public folder had 
3. Run `example.com/install` from browser

## Setup Help
### Database
Use your MySQL credentials

### Signing
1. Generate private key using 
```shell
$ openssl ecparam -genkey -name secp256k1 -rand /dev/urandom -out priv.key
```
2. The Key ID will be given you by monobank team.

### Monobank API
1. API Endpoint defaults to https://api.monobank.ua
2. Blacklisted headers is the list of headers that will be ignored in /request method. Separated by a "|"

### Push Server
#### 1. Skipping this block
- If you are not going to configure Push Server - check "Disable Push Server and skip this block" and don't fill anything in this block

#### 2. Composer will be used to install Web Push libraries
1. By default Setup script tries to execute `cd .. && php composer.phar install` in command shell. If you are going to execute Composer manually, uncheck "Install Web Push libraries via Composer automatically" and install the packages before submitting the form
2. If you don't want to make API requests to monobank, will set up webhooks later, or not going to support this feature at all, uncheck "Set webhook in Monobank API"
3. By default Setup script tries to download Composer from https://getcomposer.org/installer and install it to a parent folder of the Setup script location
4. Specify PHP Executable Path for Composer to run, if it's not `php`

#### 3. VAPID Keys
1. These keys are being generated automatically if not both not filled in
2. You can generate VAPID keys manually:
    ```shell
        $ npm install -g web-push
        $ web-push generate-vapid-keys
    ```

#### 4. Webhook Secret
- Webhook secret is a random password for monobank to prevent strangers from sending fake events. If not filled, generates automatically.

### Write & Set Up
- Script may take a while to download and install packages. Ask your hoster to temporarily increase limits if it's not enough time and the script is being killed, or try installing packages manually