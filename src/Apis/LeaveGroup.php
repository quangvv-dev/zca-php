<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/leaveGroup.ts
 *
 * Response: array{memberError: array}
 */
class LeaveGroup extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/leave");
    }

    /**
     * Leave group
     *
     * @param string $groupId - groupId to leave
     * @param bool $silent - Turn on/off silent leave group
     *
     * @note Zalo might throw an error with code 166 if you are not a member of the group
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $groupId, bool $silent = false): mixed
    {
        $requestParams = [
            "grids" => [$groupId], // API only supports leaving one group at a time
            "imei" => $this->ctx->imei,
            "silent" => $silent ? 1 : 0,
            "language" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($requestParams));
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
