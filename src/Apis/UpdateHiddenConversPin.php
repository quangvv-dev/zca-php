<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/updateHiddenConversPin.ts
 *
 * Response: ""
 */
class UpdateHiddenConversPin extends BaseApi
{
    private string $serviceURL;
    // `D` modifier: `$` must not match before a trailing newline (same as JS)
    private string $pinRegex = '/^\d{4}$/D';

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["conversation"][0]}/api/hiddenconvers/update-pin");
    }

    /**
     * Update hidden conversation pin
     *
     * @param string $pin The pin to update (must be a 4-digit number between 0000-9999)
     *
     * @throws ZaloApiError
     */
    public function __invoke(string $pin): mixed
    {
        if (!preg_match($this->pinRegex, $pin)) {
            throw new ZaloApiError("Pin must be a 4-digit number between 0000-9999");
        }

        $encryptedPin = Utils::encryptPin($pin);
        $params = [
            "new_pin" => $encryptedPin,
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
