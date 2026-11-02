<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/sendFriendRequest.ts
 *
 * Response: ""
 */
class SendFriendRequest extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/sendreq");
    }

    /**
     * Send a friend request to a user.
     *
     * @param string $msg message sent with friend request
     * @param string $userId User ID to send friend request to
     *
     * @note Zalo might throw an error with code 225 if the user is already friends with you,
     *          215 if the user might have blocked you,
     *          222 if user has already sent you a friend request, your request will be treated as acceptance instead.
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $msg, string $userId): mixed
    {
        $params = [
            "toid" => $userId,
            "msg" => $msg,
            "reqsrc" => 30,
            "imei" => $this->ctx->imei,
            "language" => $this->ctx->language,
            "srcParams" => Utils::json([
                "uidTo" => $userId,
            ]),
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
