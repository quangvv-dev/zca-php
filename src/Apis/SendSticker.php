<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendSticker.ts
 *
 * Response: array{msgId: int}
 */
class SendSticker extends BaseApi
{
    /** @var array<int, string> */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/sticker", [
                "nretry" => "0",
            ]),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/sticker", [
                "nretry" => "0",
            ]),
        ];
    }

    /**
     * Send a sticker to a thread
     *
     * @param array{id: int, cateId: int, type: int} $sticker Sticker object
     * @param string $threadId group or user id
     * @param int $type Message type (User or GroupMessage)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $sticker, string $threadId, int $type = ThreadType::User)
    {
        if (!$sticker) throw new ZaloApiError("Missing sticker");
        if (!$threadId) throw new ZaloApiError("Missing threadId");

        if (!($sticker["id"] ?? null)) throw new ZaloApiError("Missing sticker id");

        // Sometime sticker.cateId = 0, which is invalid.
        if (!isset($sticker["cateId"])) throw new ZaloApiError("Missing sticker cateId");
        if (!($sticker["type"] ?? null)) throw new ZaloApiError("Missing sticker type");

        $isGroupMessage = $type === ThreadType::Group;

        $params = [
            "stickerId" => $sticker["id"],
            "cateId" => $sticker["cateId"],
            "type" => $sticker["type"],
            "clientId" => Utils::now(),
            "imei" => $this->ctx->imei,
            "zsource" => 101,
            "toid" => $isGroupMessage ? null : $threadId,
            "grid" => $isGroupMessage ? $threadId : null,
        ];

        Utils::removeUndefinedKeys($params);

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->serviceURL[$type], [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
