<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/reuseAvatar.ts
 *
 * Response: null
 */
class ReuseAvatar extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/reuse-avatar");
    }

    /**
     * Reuse avatar
     *
     * @param string $photoId photo id from getAvatarList api
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $photoId): mixed
    {
        $params = [
            "photoId" => $photoId,
            "isPostSocial" => 0,
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
