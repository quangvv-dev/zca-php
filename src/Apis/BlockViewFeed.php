<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/blockViewFeed.ts
 *
 * Response: ""
 */
class BlockViewFeed extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/feed/block");
    }

    /**
     * Block/Unblock friend view feed by ID
     *
     * @param bool $isBlockFeed Boolean to block/unblock view feed
     * @param string $userId User ID to block/unblock view feed
     *
     * @throws ZaloApiError
     */
    public function __invoke(bool $isBlockFeed, string $userId): mixed
    {
        $params = [
            "fid" => $userId,
            "isBlockFeed" => $isBlockFeed ? 1 : 0,
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
