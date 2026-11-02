<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/addUnreadMark.ts
 *
 * Response: array{data: array{updateId: int}, status: int}
 */
class AddUnreadMark extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/conv/addUnreadMark");
    }

    /**
     * Add unread mark to conversation
     *
     * @param string $threadId Thread ID
     * @param int $type Thread type (User/Group)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $threadId, int $type = ThreadType::User)
    {
        $timestamp = Utils::now();
        $timestampString = (string) $timestamp;
        $isGroup = $type === ThreadType::Group;

        $requestParams = [
            "param" => Utils::json([
                ($isGroup ? "convsGroup" : "convsUser") => [
                    [
                        "id" => $threadId,
                        "cliMsgId" => $timestampString,
                        "fromUid" => "0",
                        "ts" => $timestamp,
                    ],
                ],
                ($isGroup ? "convsUser" : "convsGroup") => [],
                "imei" => $this->ctx->imei,
            ]),
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($requestParams));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];
            if (is_string($data["data"] ?? null)) {
                return [
                    "data" => Utils::jsonDecode($data["data"]),
                    "status" => $data["status"] ?? null,
                ];
            }

            return $result["data"];
        });
    }
}
