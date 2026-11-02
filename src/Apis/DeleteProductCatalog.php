<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/deleteProductCatalog.ts
 *
 * Response: array{item: int[], version_ls_catalog: int, version_catalog: int}
 */
class DeleteProductCatalog extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/product/mdelete");
    }

    /**
     * Delete product catalog?
     *
     * @param array{productIds: string|string[], catalogId: string} $payload payload
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        if (!is_array($payload["productIds"])) $payload["productIds"] = [$payload["productIds"]];

        $params = [
            "product_ids" => $payload["productIds"],
            "catalog_id" => $payload["catalogId"],
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
