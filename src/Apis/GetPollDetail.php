<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getPollDetail.ts
 *
 * Response: PollDetail array
 */
class GetPollDetail extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/poll/detail");
    }

    /**
     * Get poll detail
     *
     * @param int $pollId Poll ID
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(int $pollId)
    {
        if (!$pollId) throw new ZaloApiError("Missing poll id");

        $params = [
            "poll_id" => $pollId,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
