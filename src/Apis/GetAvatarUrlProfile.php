<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\AvatarSize;
use Zca\Utils;

/**
 * Port of src/apis/getAvatarUrlProfile.ts
 *
 * Response: array<string, array{avatar: string}> (keyed by user id)
 */
class GetAvatarUrlProfile extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/avatar-url");
    }

    /**
     * Get avatar url profile
     *
     * @param string|string[] $friendIds friend id(s)
     * @param int $avatarSize Avatar size (default: AvatarSize::Large)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($friendIds, int $avatarSize = AvatarSize::Large)
    {
        if (!is_array($friendIds)) $friendIds = [$friendIds];

        $params = [
            "friend_ids" => $friendIds,
            "avatar_size" => $avatarSize,
            "srcReq" => -1,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
