<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/searchSticker.ts
 *
 * Response: list of StickerBasic
 */
class SearchSticker extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["sticker"][0]}/api/message/sticker/search");
    }

    /**
     * Search stickers
     *
     * @param string $keyword Keyword to search stickers
     * @param int $limit Limit of stickers to return (default: 50)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $keyword, int $limit = 50)
    {
        $params = [
            "keyword" => $keyword,
            "limit" => $limit,
            "srcType" => 0,
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
