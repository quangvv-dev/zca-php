<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateActiveStatus.ts
 *
 * Response: array{status: bool}
 */
class UpdateActiveStatus extends BaseApi
{
    private string $pingURL;
    private string $deactiveURL;

    protected function init(): void
    {
        $this->pingURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/ping");
        $this->deactiveURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/deactive");
    }

    /**
     * Update active status?
     *
     * @param bool $active
     *
     * @throws ZaloApiError
     */
    public function __invoke(bool $active): mixed
    {
        $params = [
            "status" => $active ? 1 : 0,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $targetURL = $active ? $this->pingURL : $this->deactiveURL;
        $response = $this->utils->request($this->utils->makeURL($targetURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
