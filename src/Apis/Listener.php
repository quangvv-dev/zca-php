<?php

namespace Zca\Apis;

use Evenement\EventEmitter;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use Zca\Context;
use Zca\Errors\ZaloApiError;
use Zca\Logger;
use Zca\Models\CloseReason;
use Zca\Models\FriendEvent;
use Zca\Models\GroupDeliveredMessage;
use Zca\Models\GroupEvent;
use Zca\Models\GroupMessage;
use Zca\Models\GroupSeenMessage;
use Zca\Models\GroupTyping;
use Zca\Models\Reaction;
use Zca\Models\ThreadType;
use Zca\Models\Undo;
use Zca\Models\UserDeliveredMessage;
use Zca\Models\UserMessage;
use Zca\Models\UserSeenMessage;
use Zca\Models\UserTyping;
use Zca\Utils;

use function React\Async\async;

/**
 * Port of src/apis/listen.ts, built on ReactPHP + Pawl.
 *
 * Events (use `$listener->on($event, $callback)`):
 *  - connected: ()
 *  - disconnected: (int $code, string $reason)
 *  - closed: (int $code, string $reason)
 *  - error: (mixed $error)
 *  - typing: (UserTyping|GroupTyping $typing)
 *  - message: (UserMessage|GroupMessage $message)
 *  - old_messages: (array $messages, int $threadType)
 *  - seen_messages: (array $messages)
 *  - delivered_messages: (array $messages)
 *  - reaction: (Reaction $reaction)
 *  - old_reactions: (array $reactions, bool $isGroup)
 *  - upload_attachment: (array{fileUrl: string, fileId: string} $data)
 *  - undo: (Undo $data)
 *  - friend_event: (FriendEvent $data)
 *  - group_event: (GroupEvent $data)
 *  - cipher_key: (string $key)
 *
 * The listener runs on the ReactPHP event loop: call `start()` then `run()` (or `React\EventLoop\Loop::run()`,
 * the loop also runs automatically at the end of the script).
 * Every incoming frame is handled inside a fiber (`React\Async\async`), so blocking API calls made from
 * event callbacks (e.g. replying with `$api->sendMessage()`) work as expected.
 */
class Listener extends EventEmitter
{
    private string $wsURL;
    private string $cookie;
    private string $userAgent;

    private ?WebSocket $ws = null;
    private bool $connecting = false;

    /** @var array<string, array{count: int, max: int, times: int[]}> */
    private array $retryCount;
    private int $rotateCount;

    /** @var callable */
    private $onConnectedCallback;
    /** @var callable */
    private $onClosedCallback;
    /** @var callable */
    private $onErrorCallback;
    /** @var callable */
    private $onMessageCallback;
    private ?string $cipherKey = null;

    private bool $selfListen;
    private ?TimerInterface $pingInterval = null;

    private int $id = 0;

    /**
     * @param string[] $urls
     */
    public function __construct(private Context $ctx, private array $urls)
    {
        if (!$ctx->cookie) throw new ZaloApiError("Cookie is not available");
        if (!$ctx->userAgent) throw new ZaloApiError("User agent is not available");

        $this->wsURL = Utils::makeURL($this->ctx, $this->urls[0], [
            "t" => Utils::now(),
        ]);
        $this->retryCount = [];
        $this->rotateCount = 0;

        foreach ($ctx->settings["features"]["socket"]["retries"] ?? [] as $retry => $value) {
            $times = $value["times"];
            $this->retryCount[(string) $retry] = [
                "count" => 0,
                "max" => $value["max"] ?? 0,
                "times" => is_array($times) ? $times : [$times],
            ];
        }

        $this->cookie = $ctx->cookie->getCookieString("https://chat.zalo.me");
        $this->userAgent = $ctx->userAgent;

        $this->selfListen = (bool) $ctx->options["selfListen"];

        $this->ws = null;

        $this->onConnectedCallback = function () {};
        $this->onClosedCallback = function () {};
        $this->onErrorCallback = function () {};
        $this->onMessageCallback = function () {};
    }

    /**
     * @deprecated Use `on` method instead
     */
    public function onConnected(callable $cb): void
    {
        $this->onConnectedCallback = $cb;
    }

    /**
     * @deprecated Use `on` method instead
     */
    public function onClosed(callable $cb): void
    {
        $this->onClosedCallback = $cb;
    }

    /**
     * @deprecated Use `on` method instead
     */
    public function onError(callable $cb): void
    {
        $this->onErrorCallback = $cb;
    }

    /**
     * @deprecated Use `on` method instead
     */
    public function onMessage(callable $cb): void
    {
        $this->onMessageCallback = $cb;
    }

    private function canRetry(int $code): int|false
    {
        if (!in_array($code, $this->ctx->settings["features"]["socket"]["close_and_retry_codes"] ?? [])) return false;
        $key = (string) $code;
        if (!isset($this->retryCount[$key])) return false;
        if ($this->retryCount[$key]["count"] >= $this->retryCount[$key]["max"]) return false;
        $this->retryCount[$key]["count"]++;

        ["count" => $count, "max" => $max, "times" => $times] = $this->retryCount[$key];
        $retryTime = $count - 1 < count($times) ? $times[$count - 1] : $times[count($times) - 1];
        Logger::of($this->ctx)->verbose("Retry for code {$code} in {$retryTime}ms ({$count}/{$max})");

        return (int) $retryTime;
    }

    private function shouldRotate(int $code): bool
    {
        if (!in_array($code, $this->ctx->settings["features"]["socket"]["rotate_error_codes"] ?? [])) return false;
        if ($this->rotateCount >= count($this->urls) - 1) return false;

        return true;
    }

    private function rotateEndpoint(): void
    {
        $this->rotateCount++;
        $this->wsURL = Utils::makeURL($this->ctx, $this->urls[$this->rotateCount], [
            "t" => Utils::now(),
        ]);
        Logger::of($this->ctx)->verbose("Rotating endpoint to {$this->wsURL}");
    }

    /**
     * @param array{retryOnClose?: bool} $options
     */
    public function start(array $options = []): void
    {
        $retryOnClose = $options["retryOnClose"] ?? false;
        if ($this->ws || $this->connecting) throw new ZaloApiError("Already started");
        $this->connecting = true;

        $connector = new Connector(Loop::get());
        $headers = [
            "accept-encoding" => "gzip, deflate, br, zstd",
            "accept-language" => "en-US,en;q=0.9",
            "cache-control" => "no-cache",
            "origin" => "https://chat.zalo.me",
            "prgama" => "no-cache",
            "user-agent" => $this->userAgent,
            "cookie" => $this->cookie,
        ];

        $connector($this->wsURL, [], $headers)->then(
            function (WebSocket $ws) use ($retryOnClose) {
                $this->connecting = false;
                $this->ws = $ws;

                $ws->on("message", function (MessageInterface $msg) use ($ws) {
                    async(fn() => $this->handleMessage($ws, $msg))();
                });

                $ws->on("close", function ($code = null, $reason = null) use ($ws, $retryOnClose) {
                    if ($this->ws !== $ws) return;
                    $this->handleClose((int) ($code ?? CloseReason::AbnormalClosure), (string) ($reason ?? ""), $retryOnClose);
                });

                $ws->on("error", function ($error) {
                    ($this->onErrorCallback)($error);
                    $this->emit("error", [$error]);
                });

                ($this->onConnectedCallback)();
                $this->emit("connected");
            },
            function (\Throwable $error) use ($retryOnClose) {
                $this->connecting = false;
                ($this->onErrorCallback)($error);
                $this->emit("error", [$error]);
                $this->handleClose(CloseReason::AbnormalClosure, $error->getMessage(), $retryOnClose);
            },
        );
    }

    private function handleClose(int $code, string $reason, bool $retryOnClose): void
    {
        $this->reset();
        $this->emit("disconnected", [$code, $reason]);
        $retry = $retryOnClose ? $this->canRetry($code) : false;
        if ($retry !== false && $retryOnClose) {
            $shouldRotate = $this->shouldRotate($code);
            if ($shouldRotate) {
                $this->rotateEndpoint();
            }
            Loop::addTimer($retry / 1000, function () {
                $this->start(["retryOnClose" => true]);
            });
        } else {
            ($this->onClosedCallback)($code, $reason);
            $this->emit("closed", [$code, $reason]);
        }
    }

    private function handleMessage(WebSocket $ws, MessageInterface $message): void
    {
        $data = $message->getPayload();
        if (!$message->isBinary()) return;

        [$version, $cmd, $subCmd] = self::getHeader(substr($data, 0, 4));

        try {
            $decodedData = substr($data, 4);
            if (strlen($decodedData) == 0) return;

            $parsed = Utils::jsonDecode($decodedData);

            if ($version == 1 && $cmd == 1 && $subCmd == 1 && Utils::hasOwn($parsed, "key")) {
                $this->cipherKey = $parsed["key"];
                $this->emit("cipher_key", [$parsed["key"]]);

                if ($this->pingInterval) Loop::cancelTimer($this->pingInterval);

                $ping = function () {
                    $payload = [
                        "version" => 1,
                        "cmd" => 2,
                        "subCmd" => 1,
                        "data" => ["eventId" => Utils::now()],
                    ];
                    $this->sendWs($payload, false);
                };

                $this->pingInterval = Loop::addPeriodicTimer($this->ctx->settings["features"]["socket"]["ping_interval"] / 1000, function () use ($ping) {
                    $ping();
                });
            }

            if ($version == 1 && in_array($cmd, [501, 551]) && $subCmd == 0) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $msgs = $parsedData["msgs"];
                foreach ($msgs as $msg) {
                    if (is_array($msg["content"]) && Utils::hasOwn($msg["content"], "deleteMsg")) {
                        $undoObject = new Undo($this->ctx->uid, $msg, false);
                        if ($undoObject->isSelf && !$this->selfListen) continue;
                        $this->emit("undo", [$undoObject]);
                    } else {
                        $messageObject = new UserMessage($this->ctx->uid, $msg);
                        if ($messageObject->isSelf && !$this->selfListen) continue;
                        ($this->onMessageCallback)($messageObject);
                        $this->emit("message", [$messageObject]);
                    }
                }
            }

            if ($version == 1 && $cmd == 521 && $subCmd == 0) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $groupMsgs = $parsedData["groupMsgs"];
                foreach ($groupMsgs as $msg) {
                    if (is_array($msg["content"]) && Utils::hasOwn($msg["content"], "deleteMsg")) {
                        $undoObject = new Undo($this->ctx->uid, $msg, true);
                        if ($undoObject->isSelf && !$this->selfListen) continue;
                        $this->emit("undo", [$undoObject]);
                    } else {
                        $messageObject = new GroupMessage($this->ctx->uid, $msg);
                        if ($messageObject->isSelf && !$this->selfListen) continue;
                        ($this->onMessageCallback)($messageObject);
                        $this->emit("message", [$messageObject]);
                    }
                }
            }

            if ($version == 1 && $cmd == 601 && $subCmd == 0) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $controls = $parsedData["controls"];
                foreach ($controls as $control) {
                    if ($control["content"]["act_type"] == "file_done") {
                        $data = [
                            "fileUrl" => $control["content"]["data"]["url"],
                            "fileId" => $control["content"]["fileId"],
                        ];

                        $uploadCallback = $this->ctx->uploadCallbacks->get((string) $control["content"]["fileId"]);
                        if ($uploadCallback) $uploadCallback($data);
                        $this->ctx->uploadCallbacks->delete((string) $control["content"]["fileId"]);

                        $this->emit("upload_attachment", [$data]);
                    } elseif ($control["content"]["act_type"] == "group") {
                        // 31/08/2024
                        // for some reason, Zalo send both join and join_reject event when admin approve join requests
                        // Zalo itself doesn't seem to handle this properly either, so we gonna ignore the join_reject event

                        if ($control["content"]["act"] == "join_reject") continue;

                        $groupEventData = is_string($control["content"]["data"])
                            ? Utils::jsonDecode($control["content"]["data"])
                            : $control["content"]["data"];

                        $groupEvent = GroupEvent::initialize(
                            $this->ctx->uid,
                            $groupEventData,
                            Utils::getGroupEventType($control["content"]["act"]),
                            $control["content"]["act"],
                        );
                        if ($groupEvent->isSelf && !$this->selfListen) continue;
                        $this->emit("group_event", [$groupEvent]);
                    } elseif ($control["content"]["act_type"] == "fr") {
                        // 28/02/2025
                        // Zalo send both req and req_v2 event when user send friend request
                        // Zalo itself doesn't seem to handle this properly either, so we gonna ignore the req event

                        if ($control["content"]["act"] == "req") continue;

                        $friendEventData = is_string($control["content"]["data"])
                            ? Utils::jsonDecode($control["content"]["data"])
                            : $control["content"]["data"];

                        // Handles the case when act is "pin_create" and params is a string
                        if (
                            is_array($friendEventData) &&
                            array_key_exists("topic", $friendEventData) &&
                            is_array($friendEventData["topic"]) &&
                            array_key_exists("params", $friendEventData["topic"])
                        ) {
                            $friendEventData["topic"]["params"] = Utils::jsonDecode(Utils::jsString($friendEventData["topic"]["params"]));
                        }

                        $friendEvent = FriendEvent::initialize(
                            $this->ctx->uid,
                            is_int($friendEventData) || is_float($friendEventData) ? $control["content"]["data"] : $friendEventData,
                            Utils::getFriendEventType($control["content"]["act"]),
                        );
                        if ($friendEvent->isSelf && !$this->selfListen) continue;
                        $this->emit("friend_event", [$friendEvent]);
                    }
                }
            }

            if ($cmd == 612) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $reacts = $parsedData["reacts"];
                $reactGroups = $parsedData["reactGroups"];

                foreach ($reacts as $react) {
                    $react["content"] = Utils::jsonDecode($react["content"]);
                    $reactionObject = new Reaction($this->ctx->uid, $react, false);
                    if ($reactionObject->isSelf && !$this->selfListen) continue;

                    $this->emit("reaction", [$reactionObject]);
                }

                foreach ($reactGroups as $reactGroup) {
                    $reactGroup["content"] = Utils::jsonDecode($reactGroup["content"]);
                    $reactionObject = new Reaction($this->ctx->uid, $reactGroup, true);
                    if ($reactionObject->isSelf && !$this->selfListen) continue;

                    $this->emit("reaction", [$reactionObject]);
                }
            }

            if ($cmd == 610 || $cmd == 611) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $isGroup = $cmd == 611;

                $reacts = $parsedData[$isGroup ? "reactGroups" : "reacts"];
                $reactionObjects = array_map(fn($react) => new Reaction($this->ctx->uid, $react, $isGroup), $reacts);

                $this->emit("old_reactions", [$reactionObjects, $isGroup]);
            }

            if ($cmd == 510 && $subCmd == 1) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $msgs = $parsedData["msgs"];
                $responseMsgs = array_map(fn($msg) => new UserMessage($this->ctx->uid, $msg), $msgs);
                $this->emit("old_messages", [$responseMsgs, ThreadType::User]);
            }

            if ($cmd == 511 && $subCmd == 1) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $groupMsgs = $parsedData["groupMsgs"];
                $responseMsgs = array_map(fn($msg) => new GroupMessage($this->ctx->uid, $msg), $groupMsgs);
                $this->emit("old_messages", [$responseMsgs, ThreadType::Group]);
            }

            if ($cmd == 602 && $subCmd == 0) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $actions = $parsedData["actions"];

                foreach ($actions as $action) {
                    if ($action["act_type"] == "typing") {
                        $data = Utils::jsonDecode("{" . $action["data"] . "}");
                        if ($action["act"] == "typing") {
                            $typingObject = new UserTyping($data);
                            $this->emit("typing", [$typingObject]);
                        } elseif ($action["act"] == "gtyping") {
                            // 26/02/2025
                            // For a group with only two people, Zalo doesn't send a typing event.

                            $typingObject = new GroupTyping($data);
                            $this->emit("typing", [$typingObject]);
                        }
                    }
                }
            }

            if ($cmd == 502 && $subCmd == 0) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $deliveredMsgs = $parsedData["delivereds"] ?? null;
                $seenMsgs = $parsedData["seens"] ?? null;

                if (is_array($deliveredMsgs) && count($deliveredMsgs) > 0) {
                    $deliveredObjects = array_map(fn($delivered) => new UserDeliveredMessage($delivered), $deliveredMsgs);
                    $this->emit("delivered_messages", [$deliveredObjects]);
                }

                if (is_array($seenMsgs) && count($seenMsgs) > 0) {
                    $seenObjects = array_map(fn($seen) => new UserSeenMessage($seen), $seenMsgs);
                    $this->emit("seen_messages", [$seenObjects]);
                }
            }

            if ($cmd == 522 && $subCmd == 0) {
                $parsedData = Utils::decodeEventData($parsed, $this->cipherKey)["data"];
                $deliveredMsgs = $parsedData["delivereds"] ?? null;
                $groupSeenMsgs = $parsedData["groupSeens"] ?? null;

                if (is_array($deliveredMsgs) && count($deliveredMsgs) > 0) {
                    $deliveredObjects = array_map(
                        fn($delivered) => new GroupDeliveredMessage($this->ctx->uid, $delivered),
                        $deliveredMsgs,
                    );
                    if (!$this->selfListen)
                        $deliveredObjects = array_values(array_filter($deliveredObjects, fn($delivered) => !$delivered->isSelf));
                    $this->emit("delivered_messages", [$deliveredObjects]);
                }

                if (is_array($groupSeenMsgs) && count($groupSeenMsgs) > 0) {
                    $seenObjects = array_map(fn($seen) => new GroupSeenMessage($this->ctx->uid, $seen), $groupSeenMsgs);
                    if (!$this->selfListen) $seenObjects = array_values(array_filter($seenObjects, fn($seen) => !$seen->isSelf));
                    $this->emit("seen_messages", [$seenObjects]);
                }
            }

            if ($version == 1 && $cmd == 3000 && $subCmd == 0) {
                Logger::of($this->ctx)->error();
                Logger::of($this->ctx)->error("Another connection is opened, closing this one");
                Logger::of($this->ctx)->error();
                if ($this->ws === $ws) $ws->close(CloseReason::DuplicateConnection);
            }
        } catch (\Throwable $error) {
            ($this->onErrorCallback)($error);
            $this->emit("error", [$error]);
        }
    }

    public function stop(): void
    {
        if ($this->ws) {
            $ws = $this->ws;
            $this->reset();
            $ws->close(CloseReason::ManualClosure);
            $this->emit("disconnected", [CloseReason::ManualClosure, ""]);
            ($this->onClosedCallback)(CloseReason::ManualClosure, "");
            $this->emit("closed", [CloseReason::ManualClosure, ""]);
        }
    }

    /**
     * Run the event loop (same API as zca-php 1.x for PHP 7).
     */
    public function run(): void
    {
        Loop::run();
    }

    public function isConnected(): bool
    {
        return $this->ws !== null;
    }

    /**
     * true when `start()` was called and the listener is connected or connecting
     */
    public function isStarted(): bool
    {
        return $this->ws !== null || $this->connecting;
    }

    /**
     * @param array{version: int, cmd: int, subCmd: int, data: array<string, mixed>} $payload
     */
    public function sendWs(array $payload, bool $requireId = true): void
    {
        if ($this->ws) {
            if ($requireId) $payload["data"]["req_id"] = "req_" . $this->id++;

            $encodedData = Utils::json($payload["data"]);

            // header: version (uint8), cmd (int16 LE), subCmd (int8)
            $data = pack("C", $payload["version"]) . pack("v", $payload["cmd"] & 0xffff) . pack("c", $payload["subCmd"]) . $encodedData;

            $this->ws->send(new Frame($data, true, Frame::OP_BINARY));
        }
    }

    /**
     * Request old messages
     */
    public function requestOldMessages(int $threadType, ?string $lastMsgId = null): void
    {
        $payload = [
            "version" => 1,
            "cmd" => $threadType === ThreadType::User ? 510 : 511,
            "subCmd" => 1,
            "data" => ["first" => true, "lastId" => $lastMsgId, "preIds" => []],
        ];
        $this->sendWs($payload);
    }

    /**
     * Request old reactions
     */
    public function requestOldReactions(int $threadType, ?string $lastMsgId = null): void
    {
        $payload = [
            "version" => 1,
            "cmd" => $threadType === ThreadType::User ? 610 : 611,
            "subCmd" => 1,
            "data" => ["first" => true, "lastId" => $lastMsgId, "preIds" => []],
        ];
        $this->sendWs($payload);
    }

    private function reset(): void
    {
        $this->ws = null;
        $this->cipherKey = null;
        if ($this->pingInterval) Loop::cancelTimer($this->pingInterval);
        $this->pingInterval = null;
    }

    /**
     * @return int[] [version, cmd, subCmd]
     */
    private static function getHeader(string $buffer): array
    {
        if (strlen($buffer) < 4) {
            throw new ZaloApiError("Invalid header");
        }

        return [ord($buffer[0]), unpack("v", substr($buffer, 1, 2))[1], ord($buffer[3])];
    }
}
