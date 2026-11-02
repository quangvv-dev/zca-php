<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateQuickMessage.ts
 *
 * Response: array{item: array (QuickMessage), version: int}
 */
class UpdateQuickMessage extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["quick_message"][0]}/api/quickmessage/update");
    }

    /**
     * Update quick message
     *
     * @param array{keyword: string, title: string, media?: string|array{data: string, filename: string, metadata: array{totalSize: int, width?: int, height?: int}}} $updatePayload - The payload containing data to update the quick message
     * @param int $itemId - The id of the quick message to update
     *
     * @note Zalo might throw an error with code 212 if the itemId does not exist.
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $updatePayload, int $itemId): mixed
    {
        $isType = !($updatePayload["media"] ?? null) ? 0 : 1;

        $params = [
            "itemId" => $itemId,
            "keyword" => $updatePayload["keyword"],
            "message" => [
                "title" => $updatePayload["title"],
                "params" => "",
            ],
            "type" => $isType,
        ];

        if ($isType === 1) {
            if (!($updatePayload["media"] ?? null)) throw new ZaloApiError("Media is required");
            $uploadMedia = $this->api->uploadProductPhoto([
                "file" => $updatePayload["media"],
            ]);

            $photoId = $uploadMedia["photoId"];
            $thumbUrl = $uploadMedia["thumbUrl"];
            $normalUrl = $uploadMedia["normalUrl"];
            $hdUrl = $uploadMedia["hdUrl"];

            $params["media"] = [
                "items" => [
                    [
                        "type" => 0,
                        "photoId" => $photoId,
                        "title" => "",
                        "width" => "",
                        "height" => "",
                        "previewThumb" => $thumbUrl,
                        "rawUrl" => $normalUrl ?: $hdUrl,
                        "thumbUrl" => $thumbUrl,
                        "normalUrl" => $normalUrl ?: $hdUrl,
                        "hdUrl" => $hdUrl ?: $normalUrl,
                    ],
                ],
            ];
        }

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
