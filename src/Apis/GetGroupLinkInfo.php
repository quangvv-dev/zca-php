<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupLinkInfo.ts
 *
 * Response: array{groupId: string, name: string, desc: string, type: int, creatorId: string, avt: string, fullAvt: string, adminIds: string[], currentMems: array, admins: array, hasMoreMember: int, subType: int, totalMember: int, setting: array, globalId: string}
 */
class GetGroupLinkInfo extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/link/ginfo");
    }

    /**
     * Get group link info
     *
     * @param array{link: string, memberPage?: int} $payload The payload of the request (memberPage default: 1)
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        $params = [
            "link" => $payload["link"],
            "avatar_size" => 120,
            "member_avatar_size" => 120,
            "mpage" => $payload["memberPage"] ?? 1,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
