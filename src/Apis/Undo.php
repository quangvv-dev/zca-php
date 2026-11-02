<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/undo.ts
 *
 * Response: array{status: int}
 */
class Undo extends BaseApi
{
    /** @var array<int, string> */
    private $URLType;

    protected function init(): void
    {
        $this->URLType = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/undo"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/undomsg"),
        ];
    }

    /**
     * Undo a message
     *
     * @param array{msgId: string|int, cliMsgId: string|int} $payload Undo payload containing message ID and client message ID
     * @param string $threadId group or user id
     * @param int $type Message type (User or Group), default is User
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $payload, string $threadId, int $type = ThreadType::User)
    {
        $params = [
            "msgId" => $payload["msgId"],
            "clientId" => Utils::now(),
            "cliMsgIdUndo" => $payload["cliMsgId"],
        ];

        if ($type == ThreadType::Group) {
            $params["grid"] = $threadId;
            $params["visibility"] = 0;
            $params["imei"] = $this->ctx->imei;
        } else $params["toid"] = $threadId;

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->URLType[$type], [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
