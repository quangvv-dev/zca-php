<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupMembersInfo.ts
 *
 * Response: array{profiles: array<string, array{displayName: string, zaloName: string, avatar: string, accountStatus: int, type: int, lastUpdateTime: int, globalId: string, id: string}>, unchangeds_profile: array}
 */
class GetGroupMembersInfo extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/group/members");
    }

    /**
     * Get group information
     *
     * @param string|string[] $memberId member id or array of member ids
     *
     * @throws ZaloApiError
     */
    public function __invoke(string|array $memberId): mixed
    {
        if (!is_array($memberId)) $memberId = [$memberId];

        $params = [
            "friend_pversion_map" => array_map(fn ($id) => (str_ends_with($id, "_0") ? $id : "{$id}_0"), $memberId),
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]));

        return $this->utils->resolve($response);
    }
}
