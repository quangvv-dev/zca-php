<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupLinkDetail.ts
 *
 * Response: array{link?: string, expiration_date?: int, enabled: int} (enabled: 1 enabled, 0 disabled)
 */
class GetGroupLinkDetail extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/link/detail");
    }

    /**
     * Get group link detail
     *
     * @param string $groupId The group id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
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
