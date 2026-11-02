<?php

require __DIR__ . "/../vendor/autoload.php";

use Zca\Models\ThreadType;
use Zca\Models\UserMessage;
use Zca\Zalo;

$credentials = json_decode(file_get_contents(__DIR__ . "/credentials.json"), true);

$zalo = new Zalo(["selfListen" => false]);
$api = $zalo->login($credentials);

$api->listener->on("message", function ($message) use ($api) {
    $isPlainText = is_string($message->data["content"]);
    if (!$isPlainText || $message->isSelf) return;

    if ($message->type === ThreadType::User) {
        $api->sendMessage([
            "msg" => "echo: " . $message->data["content"],
            "quote" => $message->data,
        ], $message->threadId, $message->type);
    }
});

$api->listener->on("connected", function () {
    return print("Listener connected" . PHP_EOL);
});
$api->listener->on("closed", function ($code, $reason) {
    return print("Listener closed: {$code} {$reason}" . PHP_EOL);
});
$api->listener->on("error", function ($error) {
    return print("Listener error: " . ($error instanceof \Throwable ? $error->getMessage() : json_encode($error)) . PHP_EOL);
});

$api->listener->start(["retryOnClose" => true]);

$api->listener->run();
