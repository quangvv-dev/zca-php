<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/disableGroupLink.ts
 *
 * Response: "" (empty string)
 */
class DisableGroupLink extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/link/disable");
    }

    /**
     * Disable group link
     *
     * @param string $groupId The group id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
