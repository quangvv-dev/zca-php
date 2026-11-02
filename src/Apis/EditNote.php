<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/editNote.ts
 *
 * Response: NoteDetail (array, `params` decoded from JSON string)
 */
class EditNote extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group_board"][0]}/api/board/topic/updatev2");
    }

    /**
     * Edit an existing note in a group
     *
     * @param array{title: string, topicId: string, pinAct?: bool} $options Options for editing the note
     * @param string $groupId Group ID to create note from
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $options, string $groupId)
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
            "topicId" => $options["topicId"],
            "repeat" => 0,
            "imei" => $this->ctx->imei,
            "pinAct" => ($options["pinAct"] ?? null) ? 1 : 2,
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
            $data = $result["data"];
            if (is_string($data["params"])) {
                $data["params"] = Utils::jsonDecode($data["params"]);
            }

            return $data;
        });
    }
}
