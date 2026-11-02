<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/addQuickMessage.ts
 *
 * Response: array{item: QuickMessage, version: int}
 */
class AddQuickMessage extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["quick_message"][0]}/api/quickmessage/create");
    }

    /**
     * Add quick message
     *
     * @param array{keyword: string, title: string, media?: string|array} $addPayload The payload containing data to add the quick message
     *
     * @note Zalo might throw an error with code 821 if you have reached the limit of quick messages.
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $addPayload): mixed
    {
        $isType = !($addPayload["media"] ?? null) ? 0 : 1;

        $params = [
            "keyword" => $addPayload["keyword"],
            "message" => [
                "title" => $addPayload["title"],
                "params" => "",
            ],
            "type" => $isType,
            "imei" => $this->ctx->imei,
        ];

        if ($isType === 1) {
            if (!($addPayload["media"] ?? null)) throw new ZaloApiError("Media is required");
            $uploadMedia = $this->api->uploadProductPhoto([
                "file" => $addPayload["media"],
            ]);

            $photoId = $uploadMedia["photoId"] ?? null;
            $thumbUrl = $uploadMedia["thumbUrl"] ?? null;
            $normalUrl = $uploadMedia["normalUrl"] ?? null;
            $hdUrl = $uploadMedia["hdUrl"] ?? null;

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
