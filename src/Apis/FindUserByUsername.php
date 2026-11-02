<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\AvatarSize;
use Zca\Utils;

/**
 * Port of src/apis/findUserByUsername.ts
 *
 * Response: UserBasic
 */
class FindUserByUsername extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/search/by-user-name");
    }

    /**
     * Find user by username
     *
     * @param string $username username for find
     * @param int $avatarSize Avatar size (default: AvatarSize::Large)
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $username, int $avatarSize = AvatarSize::Large): mixed
    {
        $params = [
            "user_name" => $username,
            "avatar_size" => $avatarSize,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
