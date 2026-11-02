<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateBankAccount.ts
 *
 * Response: BankAccount (array)
 */
class UpdateBankAccount extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["zimsg"][0]}/api/transfer/update");
    }

    /**
     * Update bank account
     *
     * @param array{accountId: int, binBank: int, numAccBank: string, nameAccBank: string} $payload The payload containing the bank account information to update (binBank: BinBankCard::*)
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload): mixed
    {
        $params = [
            "account_id" => $payload["accountId"],
            "bin" => $payload["binBank"],
            "bank_number" => $payload["numAccBank"],
            "holder_name" => Utils::normalizeHolderName($payload["nameAccBank"]),
            "language" => $this->ctx->language,
        ];
        // normalizeHolderName returns `undefined` in JS, which JSON.stringify drops
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
