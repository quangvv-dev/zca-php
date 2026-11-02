<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/deleteAvatar.ts
 *
 * Response: array{delPhotoIds: string[], errMap: array<string, array{err: int}>}
 */
class DeleteAvatar extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/del-avatars");
    }

    /**
     * Delete avatar from avatar list
     *
     * @param string|string[] $photoId avatar photo ID(s) to delete - can be a single string or array of strings
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($photoId)
    {
        $photoIds = is_array($photoId) ? $photoId : [$photoId];
        $delPhotos = array_map(function ($id) {
            return ["photoId" => $id];
        }, $photoIds);

        $params = [
            "delPhotos" => Utils::json($delPhotos),
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
