<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/disperseGroup.ts
 *
 * Response: "" (empty string)
 */
class DisperseGroup extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/disperse");
    }

    /**
     * Disperse Group
     *
     * @param string $groupId Group ID to disperse Group from
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

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
