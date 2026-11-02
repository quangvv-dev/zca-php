<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getListBank.ts
 *
 * Response: array{banks: array<int, array> (BankInfo)}
 */
class GetListBank extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["zimsg"][0]}/api/transfer/conf");
    }

    /**
     * Get list bank
     *
     * @throws ZaloApiError
     */
    public function __invoke(): mixed
    {
        $params = new \stdClass();

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
