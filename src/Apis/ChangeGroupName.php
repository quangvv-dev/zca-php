<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/changeGroupName.ts
 *
 * Response: array{status: int}
 */
class ChangeGroupName extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/updateinfo");
    }

    /**
     * Change group name
     *
     * @param string $name New group name
     * @param string $groupId Group ID
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $name, string $groupId): mixed
    {
        if (strlen($name) == 0) $name = (string) Utils::now();

        $params = [
            "grid" => $groupId,
            "gname" => $name,
            "imei" => $this->ctx->imei,
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
