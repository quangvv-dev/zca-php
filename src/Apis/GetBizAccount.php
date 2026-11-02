<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getBizAccount.ts
 *
 * Response: array{biz?: array{desc: ?string, cate: int, addr: string, website: string, email: string}, setting_start_page?: ?array, pkgId: int}
 */
class GetBizAccount extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/friend/get-bizacc");
    }

    /**
     * Get biz account
     *
     * @param string $friendId The friend ID to get biz account
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $friendId)
    {
        $params = [
            "fid" => $friendId,
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
