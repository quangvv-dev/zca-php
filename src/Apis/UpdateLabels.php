<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateLabels.ts
 *
 * Response: array{labelData: array (LabelData[]), version: int, lastUpdateTime: int}
 */
class UpdateLabels extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["label"][0]}/api/convlabel/update");
    }

    /**
     * Update labels
     *
     * @param array{labelData: array, version: int} $payload label data
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): array
    {
        $params = [
            "labelData" => Utils::json($payload["labelData"]),
            "version" => $payload["version"],
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        $unFormatted = $this->utils->resolve($response);

        return [
            "labelData" => Utils::jsonDecode($unFormatted["labelData"]),
            "version" => $unFormatted["version"],
            "lastUpdateTime" => $unFormatted["lastUpdateTime"],
        ];
    }
}
