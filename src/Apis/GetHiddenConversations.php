<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getHiddenConversations.ts
 *
 * Response: array{pin: string, threads: array<int, array{is_group: int, thread_id: string}>} (is_group: 1 true, 0 false)
 */
class GetHiddenConversations extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/hiddenconvers/get-all");
    }

    /**
     * Get hidden conversations
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke()
    {
        $params = [
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
