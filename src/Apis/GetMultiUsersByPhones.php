<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\AvatarSize;
use Zca\Utils;

/**
 * Port of src/apis/getMultiUsersByPhones.ts
 *
 * Response: array<string, array> (UserBasic) keyed by phone number
 */
class GetMultiUsersByPhones extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/profile/multiget");
    }

    /**
     * Get multiple user(s) by their phone numbers.
     *
     * @param string|string[] $phoneNumbers Phone number(s)
     * @param int $avatarSize Avatar size (default: AvatarSize::Large)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($phoneNumbers, int $avatarSize = AvatarSize::Large)
    {
        if (!$phoneNumbers) throw new ZaloApiError("Missing phoneNumbers");
        if (!is_array($phoneNumbers)) $phoneNumbers = [$phoneNumbers];

        $phoneNumbers = array_map(function ($phone) {
            if (strncmp($phone, "0", strlen("0")) === 0) {
                if ($this->ctx->language == "vi") $phone = "84" . substr($phone, 1);
            }
            return $phone;
        }, $phoneNumbers);

        $params = [
            "phones" => $phoneNumbers,
            "avatar_size" => $avatarSize,
            "language" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
