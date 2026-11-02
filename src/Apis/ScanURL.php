<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/scanURL.ts
 *
 * Response: array{isSafe: bool}
 */
class ScanURL extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/scanurl");
    }

    /**
     * Scan URL to check if it is safe?
     *
     * @param string $url URL to scan
     *
     * @throws ZaloApiError When something went wrong, with `error.code`
     * - `114` - Invalid params
     */
    public function __invoke(string $url): mixed
    {
        $params = [
            "url" => $url,
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
