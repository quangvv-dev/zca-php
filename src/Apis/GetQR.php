<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getQR.ts
 *
 * Response: array<string, string> QR code keyed by user id
 */
class GetQR extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/mget-qr");
    }

    /**
     * Get QR code for users
     *
     * @param string|string[] $userId User ID or list of user IDs
     *
     * @throws ZaloApiError
     */
    public function __invoke(string|array $userId): mixed
    {
        if (is_string($userId)) $userId = [$userId];

        $params = [
            "fids" => $userId,
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
