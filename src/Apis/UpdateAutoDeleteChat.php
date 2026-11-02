<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/updateAutoDeleteChat.ts
 *
 * Response: ""
 */
class UpdateAutoDeleteChat extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/conv/autodelete/updateConvers");
    }

    /**
     * Auto delete chat
     *
     * @param int $ttl The time to live of the chat (ChatTTL::*)
     * @param string $threadId The thread ID to auto delete chat
     * @param int $type Type of thread (User or Group)
     *
     * @throws ZaloApiError
     */
    public function __invoke(int $ttl, string $threadId, int $type = ThreadType::User): mixed
    {
        $params = [
            "threadId" => $threadId,
            "isGroup" => $type === ThreadType::Group ? 1 : 0,
            "ttl" => $ttl,
            "clientLang" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
