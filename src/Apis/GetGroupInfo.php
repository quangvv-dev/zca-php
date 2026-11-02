<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getGroupInfo.ts
 *
 * Response: array{removedsGroup: string[], unchangedsGroup: string[], gridInfoMap: array<string, GroupInfo & array{memVerList: string[], pendingApprove: array{time: int, uids: ?string[]}}>}
 */
class GetGroupInfo extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/getmg-v2");
    }

    /**
     * Get group information
     *
     * @param string|string[] $groupId Group ID or list of group IDs
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($groupId)
    {
        if (!is_array($groupId)) $groupId = [$groupId];

        $gridVerMap = [];
        foreach ($groupId as $id) {
            $gridVerMap[$id] = 0;
        }

        $params = [
            // cast to object so it always serializes as `{}` like the JS reduce result
            "gridVerMap" => Utils::json((object) $gridVerMap),
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request($this->serviceURL, [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }
}
