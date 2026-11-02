<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/upgradeGroupToCommunity.ts
 *
 * Response: ""
 */
class UpgradeGroupToCommunity extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/upgrade/community");
    }

    /**
     * Upgrade group to community
     *
     * @param string $groupId Group ID upgrade
     *
     * @note Zalo account identity must be verified and the account must be over 18 to use
     * @code 185 The limit on the number of communities that can own has been reached
     * @throws ZaloApiError
     */
    public function __invoke(string $groupId): mixed
    {
        $params = [
            "grId" => $groupId,
            "language" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
