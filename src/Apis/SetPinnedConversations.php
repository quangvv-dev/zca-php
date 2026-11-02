<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/setPinnedConversations.ts
 *
 * Response: ""
 */
class SetPinnedConversations extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/pinconvers/updatev2");
    }

    /**
     * Pin and unpin conversations of threads)
     *
     * @param bool $pinned Should pin conversations
     * @param string|string[] $threadId The ID(s) of the thread
     * @param int $type Type of thread, default User
     *
     * @throws ZaloApiError
     */
    public function __invoke(bool $pinned, string|array $threadId, int $type = ThreadType::User): mixed
    {
        if (is_string($threadId)) $threadId = [$threadId];

        $params = [
            "actionType" => $pinned ? 1 : 2,
            "conversations" => $type == ThreadType::Group
                ? array_map(fn ($id) => "g{$id}", $threadId)
                : array_map(fn ($id) => "u{$id}", $threadId),
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
