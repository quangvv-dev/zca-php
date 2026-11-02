<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/lockPoll.ts
 *
 * Response: ""
 */
class LockPoll extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/poll/end");
    }

    /**
     * Lock a poll, preventing further votes.
     *
     * @param int $pollId Poll id to lock
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(int $pollId)
    {
        $params = [
            "poll_id" => $pollId,
            "imei" => $this->ctx->imei,
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
