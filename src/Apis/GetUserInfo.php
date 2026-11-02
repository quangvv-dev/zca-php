<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\AvatarSize;
use Zca\Utils;

/**
 * Port of src/apis/getUserInfo.ts
 *
 * Response: array{unchanged_profiles: array, phonebook_version: int, changed_profiles: array<string, array>}
 */
class GetUserInfo extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/friend/getprofiles/v2");
    }

    /**
     * Get user info using user id
     *
     * @param string|string[] $userId User id(s)
     * @param int $avatarSize Avatar size (default: AvatarSize::Small)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($userId, int $avatarSize = AvatarSize::Small)
    {
        if (!$userId) throw new ZaloApiError("Missing user id");

        if (!is_array($userId)) $userId = [$userId];

        $userId = array_map(function ($id) {
            if (count(explode("_", $id)) > 1) {
                return $id;
            }

            return "{$id}_0";
        }, $userId);

        $params = [
            "phonebook_version" => $this->ctx->extraVer["phonebook"],
            "friend_pversion_map" => $userId,
            "avatar_size" => $avatarSize,
            "language" => $this->ctx->language,
            "show_online_status" => 1,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
