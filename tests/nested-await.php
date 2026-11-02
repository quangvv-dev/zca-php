<?php
/**
 * PHP 7 (no fibers): a listener "message" handler blocks with Async::await (like uploadAttachment waiting
 * for the `file_done` websocket event) while the event loop keeps running.
 *
 * php tests/nested-await.php
 */
require __DIR__ . "/../vendor/autoload.php";

use React\EventLoop\Loop;
use React\Promise\Deferred;
use Zca\Apis\Listener;
use Zca\Async;
use Zca\Context;
use Zca\Cookie\CookieJar;

$ctx = new Context();
$ctx->cookie = new CookieJar();
$ctx->userAgent = "test";
$ctx->uid = "1000";
$ctx->settings = ["features" => ["socket" => ["retries" => []]]];
$listener = new Listener($ctx, ["wss://example.invalid/ws"]);

$ref = new ReflectionClass($listener);
$prop = function (string $name, $value) use ($ref, $listener) {
    $p = $ref->getProperty($name);
    $p->setAccessible(true);
    $p->setValue($listener, $value);
};
$onWsMessage = $ref->getMethod("onWsMessage");
$onWsMessage->setAccessible(true);

$ws = (new ReflectionClass(\Ratchet\Client\WebSocket::class))->newInstanceWithoutConstructor();
$prop("ws", $ws); // pretend we are connected
$frame = function (array $payload) {
    $m = new \Ratchet\RFC6455\Messaging\Message();
    $m->addFrame(new \Ratchet\RFC6455\Messaging\Frame(pack("C", 1) . pack("v", 501) . pack("C", 0) . json_encode($payload), true, \Ratchet\RFC6455\Messaging\Frame::OP_BINARY));
    return $m;
};
$msg = function (string $id) use ($frame) {
    return $frame(["encrypt" => 0, "data" => json_encode(["data" => ["msgs" => [["msgId" => $id, "uidFrom" => "42", "idTo" => "0", "content" => "hi"]]]])]);
};

$log = [];
$ticks = 0;
$tick = Loop::addPeriodicTimer(0.02, function () use (&$ticks) { $ticks++; });

$listener->on("message", function ($message) use (&$log, &$ticks) {
    $before = $ticks;
    $d = new Deferred();
    Loop::addTimer(0.2, function () use ($d) { $d->resolve("file_done"); });
    $result = Async::await($d->promise());
    $log[] = $message->data["msgId"] . ":" . $result . ":loopRan=" . ($ticks > $before ? "yes" : "no");
});

Loop::addTimer(0.05, function () use ($onWsMessage, $listener, $ws, $msg) {
    $onWsMessage->invoke($listener, $ws, $msg("1"));
    $onWsMessage->invoke($listener, $ws, $msg("2"));
});
Loop::addTimer(1.0, function () use ($prop, $tick) {
    Loop::cancelTimer($tick);
    $prop("ws", null); // disconnect -> run() returns
});

$listener->run();

echo implode(" | ", $log), PHP_EOL;
echo $log === ["1:file_done:loopRan=yes", "2:file_done:loopRan=yes"] ? "NESTED AWAIT OK" : "NESTED AWAIT FAIL", PHP_EOL;
