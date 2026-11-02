<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/deleteAutoReply.ts
 *
 * Response: array{item: int, version: int}
 */
class DeleteAutoReply extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["auto_reply"][0]}/api/autoreply/delete");
    }

    /**
     * Delete auto reply
     *
     * @param int $id id of auto reply
     *
     * @note this API used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(int $id)
    {
        $params = [
            "cliLang" => $this->ctx->language,
            "id" => $id,
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
