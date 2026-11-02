<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getFriendRequestStatus.ts
 *
 * Response: array{addFriendPrivacy: int, isSeenFriendReq: bool, is_friend: int, is_requested: int, is_requesting: int}
 */
class GetFriendRequestStatus extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/reqstatus");
    }

    /**
     * Get friend request status
     *
     * @param string $friendId friend id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $friendId): mixed
    {
        $params = [
            "fid" => $friendId,
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
