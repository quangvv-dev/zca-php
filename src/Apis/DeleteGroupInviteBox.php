<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/deleteGroupInviteBox.ts
 *
 * Response: array{delInvitaionIds: string[], errMap: array<string, array{err: int}>}
 */
class DeleteGroupInviteBox extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/inv-box/mdel-inv");
    }

    /**
     * Delete group invite box
     *
     * @param string|string[] $groupId - The group id
     * @param bool $blockFutureInvite - Whether to block future invites from this group
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($groupId, bool $blockFutureInvite = false)
    {
        $grids = is_array($groupId) ? $groupId : [$groupId];

        $params = [
            "invitations" => Utils::json(array_map(function ($grid) {
                return ["grid" => $grid];
            }, $grids)),
            "block" => $blockFutureInvite ? 1 : 0,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
