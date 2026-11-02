<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/removeUnreadMark.ts
 *
 * Response: array{data: array{updateId: int}, status: int}
 */
class RemoveUnreadMark extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/conv/removeUnreadMark");
    }

    /**
     * Remove unread mark from conversation
     *
     * @param string $threadId Thread ID
     * @param int $type Thread type (User/Group)
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $threadId, int $type = ThreadType::User): mixed
    {
        $timestamp = Utils::now();

        $isGroup = $type === ThreadType::Group;
        $requestParams = [
            "param" => Utils::json([
                ($isGroup ? "convsGroup" : "convsUser") => [$threadId],
                ($isGroup ? "convsUser" : "convsGroup") => [],
                ($isGroup ? "convsGroupData" : "convsUserData") => [
                    [
                        "id" => $threadId,
                        "ts" => $timestamp,
                    ],
                ],
                ($isGroup ? "convsUserData" : "convsGroupData") => [],
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
                    "status" => $data["status"],
                ];
            }

            return $result["data"];
        });
    }
}
