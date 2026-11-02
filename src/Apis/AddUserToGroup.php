<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/addUserToGroup.ts
 *
 * Response: array{errorMembers: string[], error_data: array<string, string[]>}
 */
class AddUserToGroup extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/invite/v2");
    }

    /**
     * Add user to existing group
     *
     * @param string|string[] $memberId User ID or list of user IDs to add
     * @param string $groupId Group ID
     *
     * @throws ZaloApiError
     */
    public function __invoke(string|array $memberId, string $groupId): mixed
    {
        if (!is_array($memberId)) $memberId = [$memberId];

        $params = [
            "grid" => $groupId,
            "members" => $memberId,
            "memberTypes" => array_map(fn () => -1, $memberId),
            "imei" => $this->ctx->imei,
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
