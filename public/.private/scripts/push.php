<?php

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;


class Push
{
    static $API = null;

    static function setup()
    {

        $VAPID = [
            'VAPID' => [
                'subject' => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['SERVER_NAME']}",
                'publicKey' => Settings::$get->push->public,
                'privateKey' => Settings::$get->push->private,
            ],
        ];

        self::$API = new WebPush($VAPID);
    }

    static function sub($endpoint, $key, $auth)
    {
        return Subscription::create([
            'endpoint' => $endpoint,
            'publicKey' => $key,
            'authToken' => $auth,
        ]);
    }

    static function reply($subscription, $payload, $flush, $options, $push_id)
    {

        self::$API->sendNotification($subscription, $payload, false, $options);

        try {
            foreach (self::$API->flush() as $report) {
                $success = !!$report->isSuccess();
            }
        } catch (Error $e) {
            $success = false;
        }

        if (!$success) {
            if ($push_id) {
                $r = DB::get()->prepare("DELETE from `subscriptions` WHERE `id` = ?");
                $r->execute([$push_id]);
                return false;
            }
        }

        return true;
    }
}
