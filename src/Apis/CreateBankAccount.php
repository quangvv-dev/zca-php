<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createBankAccount.ts
 *
 * Response: BankAccount
 */
class CreateBankAccount extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["zimsg"][0]}/api/transfer/create");
    }

    /**
     * Create bank account
     *
     * @param array{binBank: int, numAccBank: string, nameAccBank: string} $payload The payload containing the bank account information (binBank: BinBankCard::*)
     *
     * @throws ZaloApiError When something went wrong, with `error.code`
     * - `-263` - Bank account already exists
     * - `810` - Internal Zalo error, might be invalid input
     */
    public function __invoke(array $payload): mixed
    {
        $params = [
            "bin" => $payload["binBank"],
            "bank_number" => $payload["numAccBank"],
            "holder_name" => Utils::normalizeHolderName($payload["nameAccBank"]),
            "language" => $this->ctx->language,
        ];
        // normalizeHolderName returns `undefined` in zca-js, which JSON.stringify drops
        if ($params["holder_name"] === null) unset($params["holder_name"]);

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
