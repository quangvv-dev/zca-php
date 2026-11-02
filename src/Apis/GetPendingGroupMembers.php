<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getPendingGroupMembers.ts
 *
 * Response: array{time: int, users: array<int, array{uid: string, dpn: string, avatar: string, user_submit: null}>}
 */
class GetPendingGroupMembers extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/pending-mems/list");
    }

    /**
     * Get pending group members
     *
     * @param string $groupId The id of the group to get the pending members
     *
     * @note Only the group leader and deputy group leader can view
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $groupId)
    {
        $params = [
            "grid" => $groupId,
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
