<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getFriendBoardList.ts
 *
 * Response: array{data: string[], version: int}
 */
class GetFriendBoardList extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["friend_board"][0]}/api/friendboard/list");
    }

    /**
     * Get friend board list
     *
     * @param string $conversationId conversation id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $conversationId): mixed
    {
        $params = [
            "conversationId" => $conversationId,
            "version" => 0,
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
