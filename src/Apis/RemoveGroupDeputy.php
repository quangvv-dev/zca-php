<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/removeGroupDeputy.ts
 *
 * Response: ""
 */
class RemoveGroupDeputy extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/admins/remove");
    }

    /**
     * Remove group deputy
     *
     * @param string|string[] $memberId user Id or list of user Ids
     * @param string $groupId group Id
     *
     * @throws ZaloApiError
     *
     *@return mixed
     */
    public function __invoke($memberId, string $groupId)
    {
        if (!is_array($memberId)) $memberId = [$memberId];

        $params = [
            "grid" => $groupId,
            "members" => $memberId,
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
