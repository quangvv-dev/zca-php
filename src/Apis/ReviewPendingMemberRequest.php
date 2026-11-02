<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/reviewPendingMemberRequest.ts
 *
 * Response: array<string, int> memberId => ReviewPendingMemberRequestStatus (SUCCESS = 0, NOT_IN_PENDING_LIST = 170, ALREADY_IN_GROUP = 178, INSUFFICIENT_PERMISSION = 166)
 */
class ReviewPendingMemberRequest extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/pending-mems/review");
    }

    /**
     * Review pending member(s) request
     *
     * @param array{members: string|string[], isApprove: bool} $payload - The payload containing data to review the pending member(s) request
     * @param string $groupId - The id of the group to review the pending member(s) request
     *
     * @note Only the group leader and deputy group leader can review
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $payload, string $groupId): mixed
    {
        if (!is_array($payload["members"])) $payload["members"] = [$payload["members"]];

        $params = [
            "grid" => $groupId,
            "members" => $payload["members"],
            "isApprove" => $payload["isApprove"] ? 1 : 0, // 1: approve, 0: reject
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
