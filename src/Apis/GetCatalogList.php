<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getCatalogList.ts
 *
 * Response: array{items: list of CatalogItem, version: int, has_more: int}
 */
class GetCatalogList extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/catalog/list");
    }

    /**
     * Get catalog list?
     *
     * @param array{limit?: int, lastProductId?: int, page?: int}|null $payload payload (limit default: 20, page default: 0)
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(?array $payload = null)
    {
        $params = [
            "version_list_catalog" => 0,
            "limit" => $payload["limit"] ?? 20,
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
