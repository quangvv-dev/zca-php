<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/rejectFriendRequest.ts
 *
 * Response: ""
 */
class RejectFriendRequest extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/reject");
    }

    /**
     * Reject a friend request from a User
     *
     * @param string $friendId
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $friendId)
    {
        $params = [
            "fid" => $friendId,
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
