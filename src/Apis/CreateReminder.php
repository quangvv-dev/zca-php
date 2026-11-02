<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ReminderRepeatMode;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/createReminder.ts
 *
 * Response: ReminderUser (User thread) | ReminderGroup without `responseMem` (Group thread)
 */
class CreateReminder extends BaseApi
{
    /** @var array<int, string> */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/oneone/create"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/createv2"),
        ];
    }

    /**
     * Create a reminder in a group
     *
     * @param array{title: string, emoji?: string, startTime?: int, repeat?: int} $options reminder options (repeat: ReminderRepeatMode::*)
     * @param string $threadId Group ID to create note from
     * @param int $type Thread type (User or Group)
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User)
    {
        $params =
            $type === ThreadType::User
                ? [
                      "objectData" => Utils::json([
                          "toUid" => $threadId,
                          "type" => 0,
                          "color" => -16245706,
                          "emoji" => $options["emoji"] ?? "⏰",
                          "startTime" => $options["startTime"] ?? Utils::now(),
                          "duration" => -1,
                          "params" => ["title" => $options["title"]],
                          "needPin" => false,
                          "repeat" => $options["repeat"] ?? ReminderRepeatMode::None,
                          "creatorUid" => $this->ctx->uid, // Note: for some reason, you can put any valid UID here instead of your own and it still works, atleast for mobile
                          "src" => 1,
                      ]),
                      "imei" => $this->ctx->imei,
                  ]
                : [
                      "grid" => $threadId,
                      "type" => 0,
                      "color" => -16245706,
                      "emoji" => $options["emoji"] ?? "⏰",
                      "startTime" => $options["startTime"] ?? Utils::now(),
                      "duration" => -1,
                      "params" => Utils::json([
                          "title" => $options["title"],
                      ]),
                      "repeat" => $options["repeat"] ?? ReminderRepeatMode::None,
                      "src" => 1,
                      "imei" => $this->ctx->imei,
                      "pinAct" => 0,
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
