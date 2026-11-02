<?php

namespace Zca\Apis;

use Zca\Context;
use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendDeliveredEvent.ts
 *
 * Response: "" | array{status: int}
 */
class SendDeliveredEvent extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/deliveredv2"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/deliveredv2"),
        ];
    }

    /**
     * Send message delivered event
     *
     * @param bool $isSeen Whether the message is seen or not
     * @param array|null $messages List of messages to send delivered event (a single message array or a list of them),
     *   message shape: array{msgId: string, cliMsgId: string, uidFrom: string, idTo: string, msgType: string, st: int, at: int, cmd: int, ts: string|int}
     * @param int $type Messages type (User or Group), defaults to User
     *
     * @throws ZaloApiError
     */
    public function __invoke(bool $isSeen, ?array $messages, int $type = ThreadType::User): mixed
    {
        if ($messages === null) throw new ZaloApiError("messages are missing or not in a valid array format.");
        if (!array_is_list($messages)) $messages = [$messages];
        if (count($messages) === 0 || count($messages) > Context::MAX_MESSAGES_PER_SEND)
            throw new ZaloApiError("messages must contain between 1 and 50 messages.");

        // 27/02/2025
        // This can send messages from multiple groups, but to prevent potential issues,
        // we will restrict it to sending messages only within the same group.
        $idTo = $messages[0]["idTo"];
        if ($type === ThreadType::Group) {
            foreach ($messages as $msg) {
                if ($msg["idTo"] !== $idTo) throw new ZaloApiError("All messages must have the same idTo for Group thread");
            }
        }

        $msgInfos = [
            "seen" => $isSeen ? 1 : 0,
            "data" => array_map(function ($msg) {
                $st = $msg["st"] ?? null;
                $at = $msg["at"] ?? null;
                $cmd = $msg["cmd"] ?? null;
                $ts = self::parseInt($msg["ts"] ?? null);

                return [
                    "cmi" => $msg["cliMsgId"],
                    "gmi" => $msg["msgId"],
                    "si" => $msg["uidFrom"],
                    "di" => $msg["idTo"] === $this->ctx->uid ? "0" : $msg["idTo"],
                    "mt" => $msg["msgType"],
                    "st" => $st || 0 === $st ? 0 : -1,
                    "at" => $at || 0 === $at ? 0 : -1,
                    "cmd" => $cmd || 0 === $cmd ? 0 : -1,
                    "ts" => $ts || 0 === $ts ? 0 : -1,
                ];
            }, $messages),
            ...($type === ThreadType::User ? [] : ["grid" => $idTo]),
        ];

        $params = [
            "msgInfos" => Utils::json($msgInfos),
            ...($type === ThreadType::User ? [] : ["imei" => $this->ctx->imei]),
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL[$type], [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }

    /**
     * JS `parseInt(`${value}`)` — returns null for NaN
     */
    private static function parseInt(mixed $value): ?int
    {
        if (!preg_match('/^\s*([+-]?\d+)/', Utils::jsString($value), $m)) return null;

        return (int) $m[1];
    }
}
