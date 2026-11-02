<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/registerCatalog.ts
 *
 * Response: ""
 */
class RegisterCatalog extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/catalog/register");
    }

    /**
     * Register catalog?
     *
     * @param bool $enable enable or disable catalog
     *
     * @throws ZaloApiError
     */
    public function __invoke(bool $enable): mixed
    {
        $params = [
            "enable" => $enable ? 1 : 0,
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
