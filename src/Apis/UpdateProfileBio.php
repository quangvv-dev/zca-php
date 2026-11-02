<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateProfileBio.ts
 *
 * Response: ""
 */
class UpdateProfileBio extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/status");
    }

    /**
     * Update profile bio
     *
     * @param string $status status for update bio
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $status): mixed
    {
        $params = [
            "status" => $status,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
