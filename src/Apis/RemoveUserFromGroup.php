<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/removeUserFromGroup.ts
 *
 * Response: array{errorMembers: string[]}
 */
class RemoveUserFromGroup extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/kickout");
    }

    /**
     * Remove user from existing group
     *
     * @param string|string[] $memberId User ID or list of user IDs to remove
     * @param string $groupId Group ID
     *
     * @note Zalo might throw an error with code 165 if the user is not in the group, 166 if you don't have enough permissions or is not in the group
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($memberId, string $groupId)
    {
        if (!is_array($memberId)) $memberId = [$memberId];

        $params = [
            "grid" => $groupId,
            "members" => $memberId,
            "imei" => $this->ctx->imei,
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
