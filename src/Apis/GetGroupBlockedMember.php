<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupBlockedMember.ts
 *
 * Response: array{blocked_members: array<array{id: string, dName: string, zaloName: string, avatar: string, avatar_25: string, accountStatus: int, type: int}>, has_more: int}
 */
class GetGroupBlockedMember extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/blockedmems/list");
    }

    /**
     * Get group blocked member
     *
     * @param array{page?: int, count?: int} $payload payload (page default: 1, count default: 50)
     * @param string $groupId group id
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload, string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
            "page" => $payload["page"] ?? 1,
            "count" => $payload["count"] ?? 50,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
