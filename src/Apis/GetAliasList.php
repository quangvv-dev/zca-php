<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getAliasList.ts
 *
 * Response: array{items: array<array{userId: string, alias: string}>, updateTime: string}
 */
class GetAliasList extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["alias"][0]}/api/alias/list");
    }

    /**
     * Get alias list
     *
     * @param int $count Page size (default: 100)
     * @param int $page Page number (default: 1)
     *
     * @throws ZaloApiError
     */
    public function __invoke(int $count = 100, int $page = 1): mixed
    {
        $params = [
            "page" => $page,
            "count" => $count,
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
