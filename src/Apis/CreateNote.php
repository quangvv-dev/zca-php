<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/createNote.ts
 *
 * Response: NoteDetail
 */
class CreateNote extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/createv2");
    }

    /**
     * Create a note in a group
     *
     * @param array{title: string, pinAct?: bool} $options note options
     *   - title: note title
     *   - pinAct: pin action (pin note)
     * @param string $groupId group id
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $options, string $groupId): mixed
    {
        $params = [
            "grid" => $groupId,
            "type" => 0,
            "color" => -16777216,
            "emoji" => "",
            "startTime" => -1,
            "duration" => -1,
            "params" => Utils::json([
                "title" => $options["title"],
            ]),
            "repeat" => 0,
            "src" => 1,
            "imei" => $this->ctx->imei,
            "pinAct" => !empty($options["pinAct"]) ? 1 : 0,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response, function (array $result) {
            if (is_string($result["data"]["params"] ?? null)) {
                $result["data"]["params"] = Utils::jsonDecode($result["data"]["params"]);
            }

            return $result["data"];
        });
    }
}
