<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateProfile.ts
 *
 * Response: ""
 */
class UpdateProfile extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/update");
    }

    /**
     * Change account setting information
     *
     * @param array{profile: array{name: string, dob: string, gender: int}, biz?: array{cate?: int, description?: string, address?: string, website?: string, email?: string}} $payload payload
     *   - profile.dob: Date of birth in the format YYYY-MM-DD
     *   - profile.gender: Gender::*
     *   - biz.cate: BusinessCategory::*
     *
     * @note If your account is a Business Account, include the biz.cate field; otherwise the category will be removed.
     * You may leave the other biz fields empty if you don’t want to change them.
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $payload)
    {
        $biz = $payload["biz"] ?? null;
        // JSON.stringify drops `undefined` values: only keep the keys present in payload.biz
        $bizParams = [];
        foreach (["desc" => "description", "cate" => "cate", "addr" => "address", "website" => "website", "email" => "email"] as $key => $field) {
            if (is_array($biz) && array_key_exists($field, $biz)) $bizParams[$key] = $biz[$field];
        }

        $params = [
            "profile" => Utils::json([
                "name" => $payload["profile"]["name"],
                "dob" => $payload["profile"]["dob"],
                "gender" => $payload["profile"]["gender"],
            ]),
            "biz" => Utils::json((object) $bizParams),
            "language" => $this->ctx->language,
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
