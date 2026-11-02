<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Errors\ZaloApiMissingImageMetadataGetter;
use Zca\Utils;

/**
 * Port of src/apis/uploadProductPhoto.ts
 *
 * Response: array{normalUrl: string, photoId: string, finished: int, hdUrl: string, thumbUrl: string, clientFileId: int, chunkId: int}
 */
class UploadProductPhoto extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/product/upload/photo");
    }

    /**
     * Upload product photo for api quick message, product catalog or custom local storage
     *
     * @param array{file: string|array{data: string, filename: string, metadata: array{totalSize: int, width?: int, height?: int}}} $payload file path or attachment source
     *
     * @throws ZaloApiError|ZaloApiMissingImageMetadataGetter
     */
    public function __invoke(array $payload): mixed
    {
        $isSourceFilePath = is_string($payload["file"]);
        $fileMetaData = $isSourceFilePath
            ? Utils::getImageMetaData($this->ctx, $payload["file"])
            : $payload["file"]["metadata"];

        $fileSize = ($fileMetaData["totalSize"] ?? null) ?: 0;

        $fileBuffer = $isSourceFilePath
            ? file_get_contents($payload["file"])
            : $payload["file"]["data"];

        $formData = Utils::formData([
            [
                "name" => "chunkContent",
                "contents" => $fileBuffer,
                "filename" => "undefined",
                "contentType" => "application/octet-stream",
            ],
        ]);

        $params = [
            "totalChunk" => 1,
            "fileName" => "Base64_Img_Picker_" . Utils::now() . ".jpg",
            "clientId" => Utils::now(),
            "totalSize" => $fileSize,
            "imei" => $this->ctx->imei,
            "chunkId" => 1,
            "toid" => $this->ctx->loginInfo["send2me_id"],
            "featureId" => 1,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request(
            $this->utils->makeURL($this->serviceURL, [
                "params" => $encryptedParams,
            ]),
            [
                "method" => "POST",
                "headers" => $formData["headers"],
                "body" => $formData["body"],
            ],
        );

        return $this->utils->resolve($response);
    }
}
