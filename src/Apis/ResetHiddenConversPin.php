<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/resetHiddenConversPin.ts
 *
 * Response: ""
 */
class ResetHiddenConversPin extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/hiddenconvers/reset");
    }

    /**
     * Reset hidden conversation pin
     *
     * @throws ZaloApiError
     */
    public function __invoke(): mixed
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
