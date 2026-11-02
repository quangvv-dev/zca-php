<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getFriendRecommendations.ts
 *
 * Response: array{expiredDuration: int, collapseMsgListConfig: array{collapseId: int, collapseXItem: int, collapseYItem: int}, recommItems: array<array{recommItemType: int, dataInfo: array}>} (recommType: FriendRecommendationsType)
 */
class GetFriendRecommendations extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/recommendsv2/list");
    }

    /**
     * Get friend recommendations/received friend requests
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke()
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
