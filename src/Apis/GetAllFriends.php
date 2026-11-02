<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\AvatarSize;
use Zca\Utils;

/**
 * Port of src/apis/getAllFriends.ts
 *
 * Response: list of User
 */
class GetAllFriends extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/friend/getfriends");
    }

    /**
     * Get all friends
     *
     * @param int $count Page size (default: 20000)
     * @param int $page Page number (default: 1)
     * @param int $avatarSize Avatar size (default: AvatarSize::Small)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(int $count = 20000, int $page = 1, int $avatarSize = AvatarSize::Small)
    {
        $params = [
            "incInvalid" => 1,
            "page" => $page,
            "count" => $count,
            "avatar_size" => $avatarSize,
            "actiontime" => 0,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
