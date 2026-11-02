<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/editReminder.ts
 *
 * Response: ReminderUser (User thread) | ReminderGroup without `responseMem` (Group thread)
 */
class EditReminder extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/oneone/update"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/updatev2"),
        ];
    }

    /**
     * Edit an existing reminder
     *
     * @param array{title: string, topicId: string, emoji?: string, startTime?: int, repeat?: int} $options Reminder parameters (repeat: ReminderRepeatMode)
     * @param string $threadId Thread ID
     * @param int $type Thread type (User/Group)
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User): mixed
    {
        $requestParams =
            $type === ThreadType::User
                ? [
                    "objectData" => Utils::json([
                        "toUid" => $threadId,
                        "type" => 0,
                        "color" => -16777216,
                        "emoji" => $options["emoji"] ?? "",
                        "startTime" => $options["startTime"] ?? Utils::now(),
                        "duration" => -1,
                        "params" => ["title" => $options["title"]],
                        "needPin" => false,
                        "reminderId" => $options["topicId"],
                        "repeat" => $options["repeat"] ?? 0,
                    ]),
                ]
                : [
                    "grid" => $threadId,
                    "type" => 0,
                    "color" => -16777216,
                    "emoji" => $options["emoji"] ?? "",
                    "startTime" => $options["startTime"] ?? Utils::now(),
                    "duration" => -1,
                    "params" => Utils::json([
                        "title" => $options["title"],
                    ]),
                    "topicId" => $options["topicId"],
                    "repeat" => $options["repeat"] ?? 0,
                    "imei" => $this->ctx->imei,
                    "pinAct" => 2,
                ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($requestParams));
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
