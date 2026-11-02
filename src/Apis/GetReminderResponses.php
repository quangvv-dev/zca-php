<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getReminderResponses.ts
 *
 * Response: array{rejectMember: string[], acceptMember: string[]}
 */
class GetReminderResponses extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/listResponseEvent");
    }

    /**
     * Get reminder responses
     *
     * @param string $reminderId reminder id
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $reminderId): mixed
    {
        $params = [
            "eventId" => $reminderId,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
