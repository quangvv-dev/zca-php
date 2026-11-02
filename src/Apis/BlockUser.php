<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/blockUser.ts
 *
 * Response: ""
 */
class BlockUser extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend"][0]}/api/friend/block");
    }

    /**
     * Block a User
     *
     * @param string $userId The ID of the User to block
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $userId)
    {
        $params = [
            "fid" => $userId,
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
