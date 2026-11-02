<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/addGroupBlockedMember.ts
 *
 * Response: ""
 */
class AddGroupBlockedMember extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/blockedmems/add");
    }

    /**
     * Add group blocked member
     *
     * @param string|string[] $memberId member id(s)
     * @param string $groupId group id
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke($memberId, string $groupId)
    {
        if (!is_array($memberId)) $memberId = [$memberId];

        $params = [
            "grid" => $groupId,
            "members" => $memberId,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
