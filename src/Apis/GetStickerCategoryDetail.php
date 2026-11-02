<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getStickerCategoryDetail.ts
 *
 * Response: list of StickerDetail
 */
class GetStickerCategoryDetail extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["sticker"][0]}/api/message/sticker/category/sticker_detail");
    }

    /**
     * Get sticker category detail
     *
     * @param int $cateId Sticker category ID
     *
     * @throws ZaloApiError
     */
    public function __invoke(int $cateId): mixed
    {
        $params = [
            "cid" => $cateId,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
