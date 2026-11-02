<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getUnreadMark.ts
 *
 * Response: array{data: array{convsGroup: array<int, array{id: int, cliMsgId: int, fromUid: int, ts: int}>, convsUser: array<int, array{id: int, cliMsgId: int, fromUid: int, ts: int}>}, status: int}
 */
class GetUnreadMark extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/conv/getUnreadMark");
    }

    /**
     * Get unread mark
     *
     * @throws ZaloApiError
     *
     *@return mixed
     */
    public function __invoke()
    {
        $params = new \stdClass();

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];
            if (is_string($data["data"] ?? null)) {
                return [
                    "data" => Utils::jsonDecode($data["data"]),
                    "status" => $data["status"],
                ];
            }

            return $data;
        });
    }
}
