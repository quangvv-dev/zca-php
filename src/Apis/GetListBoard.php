<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\BoardType;
use Zca\Utils;

/**
 * Port of src/apis/getListBoard.ts
 *
 * Response: array{items: array<int, array{boardType: int, data: array}>, count: int} (data: PollDetail | NoteDetail | PinnedMessageDetail)
 */
class GetListBoard extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/list");
    }

    /**
     * Get list board items in a group
     *
     * @param array{page?: int, count?: int} $options The options for the request (page default: 1, count default: 20)
     * @param string $groupId The ID of the group
     *
     * @throws ZaloApiError
     *
     */
    public function __invoke(array $options, string $groupId): mixed
    {
        $requestParams = [
            "group_id" => $groupId,
            "board_type" => 0,
            "page" => $options["page"] ?? 1,
            "count" => $options["count"] ?? 20,
            "last_id" => 0,
            "last_type" => 0,
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($requestParams));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];
            foreach ($data["items"] as &$item) {
                if ($item["boardType"] != BoardType::Poll) {
                    $detailData = &$item["data"];
                    if (is_string($detailData["params"] ?? null)) {
                        $detailData["params"] = Utils::jsonDecode($detailData["params"]);
                    }
                    unset($detailData);
                }
            }
            unset($item);

            return $data;
        });
    }
}
