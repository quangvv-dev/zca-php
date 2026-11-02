<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/removeFriendAlias.ts
 *
 * Response: ""
 */
class RemoveFriendAlias extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["alias"][0]}/api/alias/remove");
    }

    /**
     * Remove friend's alias
     *
     * @param string $friendId friend id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $friendId): mixed
    {
        $params = [
            "friendId" => $friendId,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
