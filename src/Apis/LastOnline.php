<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/lastOnline.ts
 *
 * Response: array{settings: array{show_online_status: bool}, lastOnline: int}
 */
class LastOnline extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/lastOnline");
    }

    /**
     * Get last online
     *
     * @param string $uid User ID
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $uid)
    {
        $params = [
            "uid" => $uid,
            "conv_type" => 1,
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
