<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/changeAccountAvatar.ts
 *
 * Response: ""
 */
class ChangeAccountAvatar extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/profile/upavatar");
    }

    /**
     * Change account avatar
     *
     * @param string|array{data: string, filename: string, metadata: array{totalSize: int, width?: int, height?: int}} $avatarSource Attachment source, either a file path or an array with the binary data
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($avatarSource)
    {
        $isSourceFilePath = is_string($avatarSource);
        $imageMetaData = $isSourceFilePath ? Utils::getImageMetaData($this->ctx, $avatarSource) : $avatarSource["metadata"];

        $fileSize = $imageMetaData["totalSize"] ?? null ?: 0;

        $params = [
            "avatarSize" => 120,
            "clientId" => (string) ($this->ctx->uid . Utils::formatTime("%H:%M %d/%m/%Y")),
            "language" => $this->ctx->language,
            "metaData" => Utils::json([
                "origin" => [
                    "width" => $imageMetaData["width"] ?? null ?: 1080,
                    "height" => $imageMetaData["height"] ?? null ?: 1080,
                ],
                "processed" => [
                    "width" => $imageMetaData["width"] ?? null ?: 1080,
                    "height" => $imageMetaData["height"] ?? null ?: 1080,
                    "size" => $fileSize,
                ],
            ]),
        ];

        $avatarData = $isSourceFilePath ? file_get_contents($avatarSource) : $avatarSource["data"];
        $formData = Utils::formData([
            [
                "name" => "fileContent",
                "contents" => $avatarData,
                "filename" => "blob",
                "contentType" => "image/jpeg",
            ],
        ]);

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
            ]
        );

        return $this->utils->resolve($response);
    }
}
