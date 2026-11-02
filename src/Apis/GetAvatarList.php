<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getAvatarList.ts
 *
 * Response: array{albumId: string, nextPhotoId: string, hasMore: int, photos: array<array{photoId: string, thumbnail: string, url: string, bkUrl: string}>}
 */
class GetAvatarList extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/avatar-list");
    }

    /**
     * Get avatar list
     *
     * @param int $count The number of avatars to fetch (default: 50)
     * @param int $page The page number to fetch (default: 1)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(int $count = 50, int $page = 1)
    {
        $params = [
            "page" => $page,
            "albumId" => "0",
            "count" => $count,
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
