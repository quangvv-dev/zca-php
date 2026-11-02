<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Errors\ZaloApiMissingImageMetadataGetter;
use Zca\Utils;

/**
 * Port of src/apis/changeGroupAvatar.ts
 *
 * Response: ""
 */
class ChangeGroupAvatar extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/group/upavatar");
    }

    /**
     * Change group avatar
     *
     * @param string|array{data: string, filename: string, metadata: array{totalSize: int, width?: int, height?: int}} $avatarSource Attachment source, can be a file path or an Attachment object
     * @param string $groupId Group ID
     *
     * @throws ZaloApiError|ZaloApiMissingImageMetadataGetter
     */
    public function __invoke(string|array $avatarSource, string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
            "avatarSize" => 120,
            "clientId" => "g{$groupId}" . Utils::getFullTimeFromMillisecond(Utils::now()),
            "imei" => $this->ctx->imei,
        ];

        $isSourceFilePath = is_string($avatarSource);
        $imageMetaData = $isSourceFilePath ? Utils::getImageMetaData($this->ctx, $avatarSource) : $avatarSource["metadata"];

        $params["originWidth"] = ($imageMetaData["width"] ?? null) ?: 1080;
        $params["originHeight"] = ($imageMetaData["height"] ?? null) ?: 1080;

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
            ],
        );

        return $this->utils->resolve($response);
    }
}
