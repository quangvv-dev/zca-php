<?php

require __DIR__ . "/../vendor/autoload.php";

use Zca\Models\LoginQRCallbackEventType;
use Zca\Zalo;

$zalo = new Zalo();

// Login with QR code, the QR image is saved to qr.png
$api = $zalo->loginQR(["qrPath" => __DIR__ . "/qr.png"], function (array $event) {
    switch ($event["type"]) {
        case LoginQRCallbackEventType::QRCodeGenerated:
            $event["actions"]["saveToFile"]();
            break;
        case LoginQRCallbackEventType::QRCodeExpired:
            $event["actions"]["retry"]();
            break;
        case LoginQRCallbackEventType::GotLoginInfo:
            // save the credentials to login again later without scanning the QR code
            file_put_contents(__DIR__ . "/credentials.json", json_encode($event["data"], JSON_PRETTY_PRINT));
            break;
    }
});

echo "Logged in as " . $api->getOwnId() . PHP_EOL;
