<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/deleteBankAccount.ts
 *
 * Response: array{hasMore: bool, total: int, myBanks: BankAccount[]}
 */
class DeleteBankAccount extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["zimsg"][0]}/api/transfer/delete");
    }

    /**
     * Delete bank account
     *
     * @param array{accountId: int, isDefault: bool} $payload The payload containing the bank account information to delete
     *
     * @throws ZaloApiError When something went wrong, with `error.code`
     * - `114` - Invalid params
     * - `-265` - Bank account not exists
     * - `810` Internal Zalo error, maybe you're trying to delete a default bank account
     * @return mixed
     */
    public function __invoke(array $payload)
    {
        $params = [
            "account_id" => $payload["accountId"],
            "is_default" => $payload["isDefault"],
            "language" => $this->ctx->language,
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
