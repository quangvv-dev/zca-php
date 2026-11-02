<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/changeFriendAlias.ts
 *
 * Response: ""
 */
class ChangeFriendAlias extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["alias"][0]}/api/alias/update");
    }

    /**
     * Change friend's alias
     *
     * @param string $alias new alias (nickname - bietdanh)
     * @param string $friendId friend id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $alias, string $friendId): mixed
    {
        $params = [
            "friendId" => $friendId,
            "alias" => $alias,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
