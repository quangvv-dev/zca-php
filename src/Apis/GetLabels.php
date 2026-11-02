<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getLabels.ts
 *
 * Response: array{labelData: array<int, array> (LabelData), version: int, lastUpdateTime: int}
 */
class GetLabels extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["label"][0]}/api/convlabel/get");
    }

    /**
     * Get all labels
     *
     * @throws ZaloApiError
     */
    public function __invoke(): mixed
    {
        $params = [
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]));

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];
            $formattedData = [
                "labelData" => Utils::jsonDecode($data["labelData"]),
                "version" => $data["version"],
                "lastUpdateTime" => $data["lastUpdateTime"],
            ];
            return $formattedData;
        });
    }
}
