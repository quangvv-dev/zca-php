<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/removeQuickMessage.ts
 *
 * Response: array{itemIds: int[], version: int}
 */
class RemoveQuickMessage extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["quick_message"][0]}/api/quickmessage/delete");
    }

    /**
     * Remove quick message
     *
     * @param int|int[] $itemIds - The id(s) of the quick message(s) to remove (number or number[])
     *
     * @note Zalo might throw an error with code 212 if the item does not exist
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($itemIds)
    {
        $idsArray = is_array($itemIds) ? $itemIds : [$itemIds];

        $params = [
            "itemIds" => $idsArray,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
