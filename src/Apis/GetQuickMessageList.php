<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getQuickMessageList.ts
 *
 * Response: array{cursor: int, version: int, items: array<int, array> (QuickMessage)}
 */
class GetQuickMessageList extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["quick_message"][0]}/api/quickmessage/list");
    }

    /**
     * Get quick message list
     *
     * @throws ZaloApiError
     */
    public function __invoke(): mixed
    {
        $params = [
            "version" => 0,
            "lang" => 0,
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
