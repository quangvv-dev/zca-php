<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getFullAvatar.ts
 *
 * Response: array{bk_full_avatar: string, full_avatar: string}
 */
class GetFullAvatar extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/avatar");
    }

    /**
     * Get full avatar
     *
     * @param string $friendId friend id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $friendId): mixed
    {
        $params = [
            "fid" => $friendId,
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
