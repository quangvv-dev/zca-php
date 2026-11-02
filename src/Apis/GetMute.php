<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getMute.ts
 *
 * Response: array{chatEntries: array<int, array{id: string, duration: int, startTime: int, systemTime: int, currentTime: int, muteMode: int}>, groupChatEntries: array<int, array{id: string, duration: int, startTime: int, systemTime: int, currentTime: int, muteMode: int}>}
 */
class GetMute extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/getmute");
    }

    /**
     * Get mute
     *
     * @throws ZaloApiError
     */
    public function __invoke(): mixed
    {
        $params = [
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
