<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createCatalog.ts
 *
 * Response: array{item: CatalogItem, version_ls_catalog: int, version_catalog: int}
 */
class CreateCatalog extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/catalog/create");
    }

    /**
     * Create catalog?
     *
     * @param string $catalogName catalog name
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $catalogName)
    {
        $params = [
            "catalog_name" => $catalogName,
            "catalog_photo" => "",
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
