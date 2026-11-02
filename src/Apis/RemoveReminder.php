<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/removeReminder.ts
 *
 * Response: "" | int
 */
class RemoveReminder extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/oneone/remove"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/remove"),
        ];
    }

    /**
     * Remove a reminder in a (user/group)
     *
     * @param string $reminderId Reminder ID to remove reminder from
     * @param string $threadId (User/Group) ID to remove reminder from
     * @param int $type Thread type (User or Group)
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $reminderId, string $threadId, int $type = ThreadType::User): mixed
    {
        $params =
            $type === ThreadType::User
                ? [
                    "uid" => $threadId,
                    "reminderId" => $reminderId,
                ]
                : [
                    "grid" => $threadId,
                    "topicId" => $reminderId,
                    "imei" => $this->ctx->imei,
                ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL[$type], [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
