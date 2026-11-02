<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateArchivedChatList.ts
 *
 * Response: array{needResync: bool, version: int}
 */
class UpdateArchivedChatList extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["label"][0]}/api/archivedchat/update");
    }

    /**
     * Archive conversations
     *
     * @param bool $isArchived - true to archive, false to unarchive
     * @param array{id: string, type: int}|array<int, array{id: string, type: int}> $conversations - List of conversations to archive/unarchive
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(bool $isArchived, array $conversations)
    {
        $arrayIsListFunction = function (array $array): bool {
            if (function_exists('array_is_list')) {
                return array_is_list($array);
            }
            if ($array === []) {
                return true;
            }
            $current_key = 0;
            foreach ($array as $key => $noop) {
                if ($key !== $current_key) {
                    return false;
                }
                ++$current_key;
            }
            return true;
        };
        if (!$arrayIsListFunction($conversations)) {
            $conversations = [$conversations];
        }
        $params = [
            "actionType" => $isArchived ? 0 : 1,
            "ids" => $conversations,
            "imei" => $this->ctx->imei,
            "version" => Utils::now(),
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
