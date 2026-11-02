<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateProductCatalog.ts
 *
 * Response: array{item: array (ProductCatalogItem), version_ls_catalog: int, version_catalog: int}
 */
class UpdateProductCatalog extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["catalog"][0]}/api/prodcatalog/product/update");
    }

    /**
     * Update product catalog?
     *
     * @param array{catalogId: string, productId: string, productName: string, price: string, description: string, createTime: int, files?: array<int, string|array>, product_photos?: string[]} $payload payload
     *   - files: Upto 5 media files are allowed, will be ignored if product_photos is provided
     *   - product_photos: List of product photo URLs, upto 5. You can manually get the URL using `uploadProductPhoto` api
     *
     * @note this API is used for zBusiness - Maximum 5 media files are supported
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        $productPhoto = ($payload["product_photos"] ?? null) ?: [];

        // JS: `payload.files && payload.files.length == 0` (an empty array is truthy in JS)
        if (isset($payload["files"]) && count($payload["files"]) == 0) {
            if (count($payload["files"]) > 5) {
                throw new ZaloApiError("Maximum 5 media files are allowed");
            }

            foreach ($payload["files"] as $mediaFile) {
                $uploadMedia = $this->api->uploadProductPhoto([
                    "file" => $mediaFile,
                ]);

                $url = ($uploadMedia["normalUrl"] ?? null) ?: $uploadMedia["hdUrl"];
                $productPhoto[] = $url;
            }
        }

        if (count($productPhoto) > 5) {
            throw new ZaloApiError("Maximum 5 media files are allowed");
        }

        $params = [
            "product_id" => $payload["productId"],
            "product_name" => $payload["productName"],
            "price" => $payload["price"],
            "description" => $payload["description"],
            "product_photos" => $productPhoto,
            "catalog_id" => $payload["catalogId"],
            "currency_unit" => "₫",
            "create_time" => $payload["createTime"],
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
