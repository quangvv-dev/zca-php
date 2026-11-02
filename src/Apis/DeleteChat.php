<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/deleteChat.ts
 *
 * Response: array{status: int}
 */
class DeleteChat extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/deleteconver", [
                "nretry" => 0,
            ]),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/deleteconver", [
                "nretry" => 0,
            ]),
        ];
    }

    /**
     * Delete chat
     *
     * @param array{ownerId: string, cliMsgId: string, globalMsgId: string} $lastMessage Last message info
     *   - ownerId: Last message owner ID to delete backwards
     *   - cliMsgId: Last message client ID to delete backwards
     *   - globalMsgId: Last message global ID to delete backwards
     * @param string $threadId Thread ID
     * @param int $type Thread type
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $lastMessage, string $threadId, int $type = ThreadType::User): mixed
    {
        $timestampString = (string) Utils::now();

        $params =
            $type === ThreadType::User
                ? [
                      "toid" => $threadId,
                      "cliMsgId" => $timestampString,
                      "conver" => $lastMessage,
                      "onlyMe" => 1,
                      "imei" => $this->ctx->imei,
                  ]
                : [
                      "grid" => $threadId,
                      "cliMsgId" => $timestampString,
                      "conver" => $lastMessage,
                      "onlyMe" => 1,
                      "imei" => $this->ctx->imei,
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
}
