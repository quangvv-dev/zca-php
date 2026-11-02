<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getAutoDeleteChat.ts
 *
 * Response: array{convers: array<array{destId: string, isGroup: bool, ttl: int, createdAt: int}>}
 */
class GetAutoDeleteChat extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/conv/autodelete/getConvers");
    }

    /**
     * Get auto delete chat
     *
     * @throws ZaloApiError
     *
     *@return mixed
     */
    public function __invoke()
    {
        $params = new \stdClass();

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
