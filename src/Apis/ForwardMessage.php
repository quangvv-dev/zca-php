<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/forwardMessage.ts
 *
 * Response: array{success: array<array{clientId: string, msgId: string}>, fail: array<array{clientId: string, error_code: string}>}
 */
class ForwardMessage extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/mforward"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/group/mforward"),
        ];
    }

    /**
     * Forward message to multiple threads
     *
     * @param array{message: string, ttl?: int, reference?: array{id: string, ts: int, logSrcType: int, fwLvl: int}} $payload Forward message payload
     * @param string[] $threadIds Thread ID(s)
     * @param int $type Thread type (User/Group)
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload, array $threadIds, int $type = ThreadType::User): mixed
    {
        if (!($payload["message"] ?? null)) throw new ZaloApiError("Missing message content");
        if (!$threadIds || count($threadIds) === 0) throw new ZaloApiError("Missing thread IDs");

        $timestamp = Utils::now();
        $clientId = (string) $timestamp;

        $msgInfo = [
            "message" => $payload["message"],
        ];
        // `reference: undefined` is dropped by JSON.stringify
        if ($payload["reference"] ?? null) {
            $msgInfo["reference"] = Utils::json([
                "type" => 3,
                "data" => Utils::json($payload["reference"]),
            ]);
        }

        $decorLog = ($payload["reference"] ?? null)
            ? [
                "fw" => [
                    "pmsg" => [
                        "st" => 1,
                        "ts" => $payload["reference"]["ts"],
                        "id" => $payload["reference"]["id"],
                    ],
                    "rmsg" => [
                        "st" => 1,
                        "ts" => $payload["reference"]["ts"],
                        "id" => $payload["reference"]["id"],
                    ],
                    "fwLvl" => $payload["reference"]["fwLvl"],
                ],
            ]
            : null;

        if ($type === ThreadType::User) {
            $params = [
                "toIds" => array_map(fn ($threadId) => [
                    "clientId" => $clientId,
                    "toUid" => $threadId,
                    "ttl" => $payload["ttl"] ?? 0,
                ], $threadIds),
                "imei" => $this->ctx->imei,
                "ttl" => $payload["ttl"] ?? 0,
                "msgType" => "1",
                "totalIds" => count($threadIds),
                "msgInfo" => Utils::json($msgInfo),
                "decorLog" => Utils::json($decorLog),
            ];
        } else {
            $params = [
                "grids" => array_map(fn ($threadId) => [
                    "clientId" => $clientId,
                    "grid" => $threadId,
                    "ttl" => $payload["ttl"] ?? 0,
                ], $threadIds),
                "ttl" => $payload["ttl"] ?? 0,
                "msgType" => "1",
                "totalIds" => count($threadIds),
                "msgInfo" => Utils::json($msgInfo),
                "decorLog" => Utils::json($decorLog),
            ];
        }

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
}
