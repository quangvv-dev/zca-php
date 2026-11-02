<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createAutoReply.ts
 *
 * Response: array{item: AutoReplyItem, version: int}
 */
class CreateAutoReply extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["auto_reply"][0]}/api/autoreply/create");
    }

    /**
     * Create auto reply
     *
     * @param array{content: string, isEnable: bool, startTime: int, endTime: int, scope: int, uids?: string|string[]} $payload payload (scope: AutoReplyScope::*)
     *
     * @note this API used for zBusiness
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $payload)
    {
        $uids = is_array($payload["uids"] ?? null) ? $payload["uids"] : [$payload["uids"] ?? null];
        $resultUids = ($payload["scope"] === 2 || $payload["scope"] === 3) ? $uids : [];

        $params = [
            "cliLang" => $this->ctx->language,
            "enable" => $payload["isEnable"],
            "content" => $payload["content"],
            "startTime" => $payload["startTime"],
            "endTime" => $payload["endTime"],
            "recurrence" => ["RRULE:FREQ=DAILY;"],
            "scope" => $payload["scope"],
            "uids" => $resultUids,
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
