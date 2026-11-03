<?php

/**
 * Sync messages from the phone (zca-js PR #269):
 * pullMobile() asks the phone to send its messages, the phone answers through `sync_event` events,
 * then getSyncData() downloads the encrypted .db.crypt backup.
 */

require __DIR__ . "/../vendor/autoload.php";

use Zca\Models\SyncEvent;
use Zca\Models\SyncEventType;
use Zca\Utils;
use Zca\Zalo;

$credentials = json_decode(file_get_contents(__DIR__ . "/credentials.json"), true);
$api = (new Zalo())->login($credentials);

$keys = Utils::generateRSAKeyPair();

$api->listener->on("sync_event", function (SyncEvent $event) use ($api, $keys) {
    if ($event->type == SyncEventType::SYNCMSG_INFO) {
        // This get the file and also save it to the specified path, you can also handle the file stream (returnStream => true)
        $result = $api->getSyncData([
            "url" => $event->data["url"],
            "encrypted_key" => $event->data["encrypted_key"],
            "file_name" => $event->data["file_name"],
            "file_size" => $event->data["file_size"],
            "checksum_code" => $event->data["checksum_code"],
            "from_seq_id" => $event->data["from_seq_id"],
            "is_full_transfer" => $event->data["is_full_transfer"],
            "savePath" => __DIR__ . "/sync_data",
        ]);

        if ($result["success"] && isset($result["data"])) {
            echo "File saved to: " . $result["data"]["file_path"] . PHP_EOL;
            echo "Encrypted key: " . $result["data"]["encrypted_key"] . PHP_EOL;

            // AES key (32 bytes) of the .db.crypt file, encrypted with our RSA public key
            $aesKey = Utils::decryptWithPrivateKey($keys["privateKey"], $result["data"]["encrypted_key"]);
            echo "AES key: " . bin2hex($aesKey) . PHP_EOL;
        } else {
            echo "Error syncing data: " . ($result["error"] ?? "") . PHP_EOL;
        }
    } elseif ($event->type == SyncEventType::TRANSFER_ERROR) {
        echo "Transfer error: " . $event->data["error_msg"] . PHP_EOL;
    }

    if ($event->type != SyncEventType::USER_CONFIRM) $api->listener->stop();
});

$api->listener->on("connected", function () use ($api, $keys) {
    $api->pullMobile([
        "public_key" => $keys["publicKeyBase64"],
    ]);
    echo "Please confirm the sync on your phone" . PHP_EOL;
});

$api->listener->start();
$api->listener->run();
