<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/joinGroupInviteBox.ts
 *
 * Response: ""
 */
class JoinGroupInviteBox extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/inv-box/join");
    }

    /**
     * Join group invite box
     *
     * @param string $groupId - The group id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
            "lang" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
