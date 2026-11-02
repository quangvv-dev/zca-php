<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getProductCatalogList.ts
 *
 * Response: array{items: array<int, array> (ProductCatalogItem), version: int, has_more: int}
 */
class GetProductCatalogList extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/product/list");
    }

    /**
     * Get product catalog list?
     *
     * @param array{catalogId: string, limit?: int, versionCatalog?: int, lastProductId?: string, page?: int} $payload payload (limit default: 100, page default: 0)
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        $params = [
            "catalog_id" => $payload["catalogId"],
            "limit" => $payload["limit"] ?? 100,
            "version_catalog" => $payload["versionCatalog"] ?? 0,
            "last_product_id" => $payload["lastProductId"] ?? -1,
            "page" => $payload["page"] ?? 0,
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
