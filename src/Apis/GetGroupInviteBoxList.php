<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupInviteBoxList.ts
 *
 * Response: array{invitations: array<int, array{groupInfo: array, inviterInfo: array, grCreatorInfo: array, expiredTs: string, type: int}>, total: int, hasMore: bool}
 */
class GetGroupInviteBoxList extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/inv-box/list");
    }

    /**
     * Get group invite box list
     *
     * @param array{mpage?: int, page?: int, invPerPage?: int, mcount?: int}|null $payload The payload of the request
     *
     * @throws ZaloApiError
     */
    public function __invoke(?array $payload = null): mixed
    {
        $params = [
            "mpage" => $payload["mpage"] ?? 1,
            "page" => $payload["page"] ?? 0,
            "invPerPage" => $payload["invPerPage"] ?? 12,
            "mcount" => $payload["mcount"] ?? 10,
            "lastGroupId" => null, // @TODO: check type
            "avatar_size" => 120,
            "member_avatar_size" => 120,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
