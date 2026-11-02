<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/inviteUserToGroups.ts
 *
 * Response: array{grid_message_map: array<string, array{error_code: int, error_message: string, data: ?string}>}
 */
class InviteUserToGroups extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/invite/multi");
    }

    /**
     * Invite user to group
     *
     * @param string $userId member ID
     * @param string|string[] $groupId group ID(s)
     *
     * @throws ZaloApiError
     *
     */
    public function __invoke(string $userId, string|array $groupId): mixed
    {
        $params = [
            "grids" => is_array($groupId) ? $groupId : [$groupId],
            "member" => $userId,
            "memberType" => -1,
            "srcInteraction" => 2,
            "clientLang" => $this->ctx->language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
