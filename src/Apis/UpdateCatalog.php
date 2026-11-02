<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateCatalog.ts
 *
 * Response: array{item: array (CatalogItem), version_ls_catalog: int, version_catalog: int}
 */
class UpdateCatalog extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/catalog/update");
    }

    /**
     * Update catalog?
     *
     * @param array{catalogId: string, catalogName: string} $payload payload
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $payload)
    {
        $params = [
            "catalog_id" => $payload["catalogId"],
            "catalog_name" => $payload["catalogName"],
            "catalog_photo" => "", // "" is defaut don't upload photo
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
