<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getReminder.ts
 *
 * Response: ReminderGroup array
 */
class GetReminder extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/getReminder");
    }

    /**
     * Get reminder details from a group
     *
     * @param string $reminderId Reminder ID
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $reminderId): mixed
    {
        $params = [
            "eventId" => $reminderId,
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
