<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/getListReminder.ts
 *
 * Response: list of reminders (ReminderUser & ReminderGroup & {groupId, eventType, responseMem, repeatInfo, repeatData})
 */
class GetListReminder extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/oneone/list"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/listReminder"),
        ];
    }

    /**
     * Get list reminder
     *
     * @param array{page?: int, count?: int} $options The options for the request (page default: 1, count default: 20)
     * @param string $threadId The ID of the thread
     * @param int $type The type of the thread (User or Group)
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User): mixed
    {
        $requestParams = [
            "objectData" => Utils::json(
                $type === ThreadType::User
                    ? [
                        "uid" => $threadId,
                        "board_type" => 1,
                        "page" => $options["page"] ?? 1,
                        "count" => $options["count"] ?? 20,
                        "last_id" => 0,
                        "last_type" => 0,
                    ]
                    : [
                        "group_id" => $threadId,
                        "board_type" => 1,
                        "page" => $options["page"] ?? 1,
                        "count" => $options["count"] ?? 20,
                        "last_id" => 0,
                        "last_type" => 0,
                    ],
            ),
        ];
        if ($type === ThreadType::Group) $requestParams["imei"] = $this->ctx->imei;

        $encryptedParams = $this->utils->encodeAES(Utils::json($requestParams));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL[$type], ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response, function (array $result) {
            return Utils::jsonDecode($result["data"]);
        });
    }
}
