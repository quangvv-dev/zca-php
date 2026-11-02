<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/deleteMessage.ts
 *
 * Response: array{status: int}
 */
class DeleteMessage extends BaseApi
{
    /** @var array<int, string> */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/delete"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/deletemsg"),
        ];
    }

    /**
     * Delete a message
     *
     * @param array{data: array{cliMsgId: string, msgId: string, uidFrom: string}, threadId: string, type?: int} $dest Delete target
     * @param bool $onlyMe Delete message for only you
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $dest, bool $onlyMe = false)
    {
        $threadId = $dest["threadId"];
        $type = $dest["type"] ?? ThreadType::User;
        $data = $dest["data"];
        $isGroup = $type === ThreadType::Group;
        $isSelf = (string) $this->ctx->uid === (string) $data["uidFrom"];

        if ($isSelf && $onlyMe === false)
            throw new ZaloApiError("To delete your message for everyone, use undo api instead");

        if (!$isGroup && $onlyMe === false) throw new ZaloApiError("Can't delete message for everyone in a private chat");

        $params = [
            ($isGroup ? "grid" : "toid") => $threadId,
            "cliMsgId" => Utils::now(),
            "msgs" => [
                [
                    "cliMsgId" => $data["cliMsgId"],
                    "globalMsgId" => $data["msgId"],
                    "ownerId" => $data["uidFrom"],
                    "destId" => $threadId,
                ],
            ],
            "onlyMe" => $onlyMe ? 1 : 0,
        ];

        if (!$isGroup) {
            $params["imei"] = $this->ctx->imei;
        }

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
