<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\GroupMessage;
use Zca\Utils;

/**
 * Port of src/apis/getGroupChatHistory.ts
 *
 * Response: array{lastActionId: string, lastActionIdOther: string, more: int, groupMsgs: GroupMessage[]}
 */
class GetGroupChatHistory extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/history");
    }

    /**
     * Get group chat history
     *
     * @param string $groupId group id
     * @param int $count count of messages to return (default: 50)
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $groupId, int $count = 50): mixed
    {
        $params = [
            "grid" => $groupId,
            "count" => $count,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];

            if (is_string($data)) {
                $data = Utils::jsonDecode($data);
            }

            for ($i = 0; $i < count($data["groupMsgs"]); $i++) {
                $data["groupMsgs"][$i] = new GroupMessage($this->ctx->uid, $data["groupMsgs"][$i]);
            }

            return $data;
        });
    }
}
