<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getRelatedFriendGroup.ts
 *
 * Response: array{groupRelateds: array<string, string[]>} (keyed by friend id)
 */
class GetRelatedFriendGroup extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/group/related");
    }

    /**
     * Get related friend group
     *
     * @param string|string[] $friendId friend ids
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($friendId)
    {
        $friendIds = is_array($friendId) ? $friendId : [$friendId];

        $params = [
            "friend_ids" => Utils::json($friendIds),
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
