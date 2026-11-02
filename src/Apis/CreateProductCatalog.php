<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createProductCatalog.ts
 *
 * Response: array{item: ProductCatalogItem, version_ls_catalog: int, version_catalog: int}
 */
class CreateProductCatalog extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/product/create");
    }

    /**
     * Create product catalog?
     *
     * @param array{catalogId: string, productName: string, price: string, description: string, files?: array<string|array>, product_photos?: string[]} $payload payload
     *   - files: Upto 5 media files are allowed, will be ignored if product_photos is provided
     *   - product_photos: List of product photo URLs, upto 5. You can manually get the URL using `uploadProductPhoto` api
     *
     * @note this API is used for zBussiness - Maximum 5 media files are supported
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        $productPhoto = ($payload["product_photos"] ?? null) ?: [];

        // NOTE: kept identical to zca-js (`payload.files && payload.files.length == 0`)
        if (isset($payload["files"]) && count($payload["files"]) == 0) {
            if (count($payload["files"]) > 5) {
                throw new ZaloApiError("Maximum 5 media files are allowed");
            }

            foreach ($payload["files"] as $mediaFile) {
                $uploadMedia = $this->api->uploadProductPhoto([
                    "file" => $mediaFile,
                ]);

                $url = ($uploadMedia["normalUrl"] ?? null) ?: ($uploadMedia["hdUrl"] ?? null);
                $productPhoto[] = $url;
            }
        }

        if (count($productPhoto) > 5) {
            throw new ZaloApiError("Maximum 5 media files are allowed");
        }

        $params = [
            "product_name" => $payload["productName"],
            "price" => $payload["price"],
            "description" => $payload["description"],
            "product_photos" => $productPhoto,
            "catalog_id" => $payload["catalogId"],
            "currency_unit" => "₫", // $
            "create_time" => Utils::now(),
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
