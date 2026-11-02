<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/enableGroupLink.ts
 *
 * Response: array{link: string, expiration_date: int, enabled: int}
 */
class EnableGroupLink extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/link/new");
    }

    /**
     * Enable and create new group link
     *
     * @param string $groupId The group id
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $groupId)
    {
        $params = [
            "grid" => $groupId,
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
