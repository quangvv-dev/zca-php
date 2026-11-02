<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getStickers.ts
 *
 * Response: int[] sticker ids
 */
class GetStickers extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        // zca-js interpolates the whole array here (`${api.zpwServiceMap.sticker}`)
        $this->serviceURL = $this->utils->makeURL(implode(",", $this->api->zpwServiceMap["sticker"]) . "/api/message/sticker");
    }

    /**
     * Get stickers by keyword
     *
     * @param string $keyword Keyword to search for
     * @return int[] Sticker IDs
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $keyword): mixed
    {
        if (!$keyword) throw new ZaloApiError("Missing keyword");

        $params = [
            "keyword" => $keyword,
            "gif" => 1,
            "guggy" => 0,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $finalServiceUrl = Utils::appendPath($this->serviceURL, "/suggest/stickers");

        $response = $this->utils->request(
            $this->utils->makeURL($finalServiceUrl, [
                "params" => $encryptedParams,
            ]),
        );

        return $this->utils->resolve($response, function (array $result) {
            $suggestions = $result["data"];
            $stickerIds = [];

            // @TODO: Implement these
            // suggestions.sugg_guggy, suggestions.sugg_gif
            if ($suggestions["sugg_sticker"] ?? null)
                foreach ($suggestions["sugg_sticker"] as $sticker) $stickerIds[] = $sticker["sticker_id"];

            return $stickerIds;
        });
    }
}
