<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/joinGroupLink.ts
 *
 * Response: ""
 */
class JoinGroupLink extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/link/join");
    }

    /**
     * Join group via invite link
     *
     * @note Zalo might throw an error with code 240 if the group enabled membership approval, 178 if you are already a member.
     *
     * @param string $link - The link join group
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $link): mixed
    {
        $params = [
            "link" => $link,
            "clientLang" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
