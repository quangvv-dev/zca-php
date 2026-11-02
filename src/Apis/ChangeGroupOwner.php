<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/changeGroupOwner.ts
 *
 * Response: array{time: int}
 */
class ChangeGroupOwner extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/change-owner");
    }

    /**
     * Change group owner
     *
     * @param string $memberId User Id of new group owner
     * @param string $groupId Group Id
     * @note Be careful when changing the key, as it will result in losing group admin rights
     *
     * @throws ZaloApiError
     *
     */
    public function __invoke(string $memberId, string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
            "newAdminId" => $memberId,
            "imei" => $this->ctx->imei,
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
