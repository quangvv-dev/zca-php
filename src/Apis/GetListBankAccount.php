<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getListBankAccount.ts
 *
 * Response: array{hasMore: bool, total: int, myBanks: array<int, array> (BankAccount)}
 */
class GetListBankAccount extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["zimsg"][0]}/api/transfer/list");
    }

    /**
     * Get list bank accounts
     *
     * @param int $page Page number (default: 0)
     * @param int $limit Number of items to retrieve (default: 20)
     *
     * @throws ZaloApiError When something went wrong, with `error.code`
     * - `114` - Invalid params
     * @return mixed
     */
    public function __invoke(int $page = 0, int $limit = 20)
    {
        $params = [
            "page" => $page,
            "limit" => $limit,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
