<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getSentFriendRequest.ts
 *
 * Response: array<string, array{userId: string, zaloName: string, displayName: string, avatar: string, globalId: string, bizPkg: array, fReqInfo: array{message: string, src: int, time: int}}> keyed by user id
 */
class GetSentFriendRequest extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/requested/list");
    }

    /**
     * Get friend requested
     *
     * @note Zalo might throw an error with code 112 if you have no friend requests.
     *
     * @throws ZaloApiError
     */
    public function __invoke(): mixed
    {
        $params = [
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
