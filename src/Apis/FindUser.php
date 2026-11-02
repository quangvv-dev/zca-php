<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\AvatarSize;
use Zca\Utils;

/**
 * Port of src/apis/findUser.ts
 *
 * Response: UserBasic
 */
class FindUser extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/profile/get");
    }

    /**
     * Find user by phone number
     *
     * @param string $phoneNumber Phone number
     * @param int $avatarSize Avatar size (default: AvatarSize::Large)
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $phoneNumber, int $avatarSize = AvatarSize::Large): mixed
    {
        if (!$phoneNumber) throw new ZaloApiError("Missing phoneNumber");
        if (str_starts_with($phoneNumber, "0")) {
            if ($this->ctx->language == "vi") $phoneNumber = "84" . substr($phoneNumber, 1);
        }

        $params = [
            "phone" => $phoneNumber,
            "avatar_size" => $avatarSize,
            "language" => $this->ctx->language,
            "imei" => $this->ctx->imei,
            "reqSrc" => 40,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        // new URL(serviceURL) + searchParams.append("params", encryptedParams)
        $finalServiceUrl = $this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams], false);

        $response = $this->utils->request(
            $this->utils->makeURL($finalServiceUrl, [
                "params" => $encryptedParams,
            ]),
        );

        return $this->utils->resolve($response, function (array $result) {
            if ($result["error"] && $result["error"]["code"] != 216)
                throw new ZaloApiError($result["error"]["message"], $result["error"]["code"]);

            return $result["data"];
        });
    }
}
