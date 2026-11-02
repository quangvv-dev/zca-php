<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getStickersDetail.ts
 *
 * Response: list of StickerDetail
 */
class GetStickersDetail extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        // zca-js interpolates the whole array here (`${api.zpwServiceMap.sticker}`)
        $this->serviceURL = $this->utils->makeURL(implode(",", $this->api->zpwServiceMap["sticker"]) . "/api/message/sticker/sticker_detail");
    }

    /**
     * Get stickers detail
     *
     * @param int|int[] $stickerIds Sticker id(s)
     *
     * @throws ZaloApiError
     */
    public function __invoke($stickerIds): array
    {
        if (!$stickerIds) throw new ZaloApiError("Missing sticker id");
        if (!is_array($stickerIds)) $stickerIds = [$stickerIds];
        if (count($stickerIds) == 0) throw new ZaloApiError("Missing sticker id");

        $stickers = [];
        // Promise.allSettled: failed requests are skipped
        foreach ($stickerIds as $stickerId) {
            try {
                $stickers[] = $this->getStickerDetail($stickerId);
            } catch (\Throwable $exception) {
            }
        }

        return $stickers;
    }

    /**
     * @return mixed
     */
    private function getStickerDetail(int $stickerId)
    {
        $params = [
            "sid" => $stickerId,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request(
            $this->utils->makeURL($this->serviceURL, [
                "params" => $encryptedParams,
            ])
        );

        return Utils::resolveResponse($this->ctx, $response);
    }
}
