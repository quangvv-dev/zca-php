<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/deleteCatalog.ts
 *
 * Response: ""
 */
class DeleteCatalog extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/catalog/delete");
    }

    /**
     * Delete catalog?
     *
     * @param string $catalogId catalog id
     *
     * @note this API is used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $catalogId)
    {
        $params = [
            "catalog_id" => $catalogId,
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
