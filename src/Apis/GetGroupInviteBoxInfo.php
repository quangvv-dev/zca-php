<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupInviteBoxInfo.ts
 *
 * Response: array{groupInfo: GroupInfo & array{topic?: GroupTopic}, inviterInfo: array, grCreatorInfo: array, expiredTs: string, type: int}
 */
class GetGroupInviteBoxInfo extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/inv-box/inv-info");
    }

    /**
     * Get group invite box info
     *
     * @param array{groupId: string, mpage?: int, mcount?: int} $payload - The payload of the request
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $payload)
    {
        $params = [
            "grId" => $payload["groupId"],
            "mcount" => $payload["mcount"] ?? 10,
            "mpage" => $payload["mpage"] ?? 1,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];
            $topic = $data["groupInfo"]["topic"];
            if (is_string($topic["params"])) {
                $params = Utils::jsonDecode($topic["params"]);
                if (is_string($params["extra"] ?? null)) {
                    $params["extra"] = Utils::jsonDecode($params["extra"]);
                }

                $topic["params"] = $params;
            }
            // JS mutates the topic object in place
            $data["groupInfo"]["topic"] = $topic;

            return $data;
        });
    }
}
