<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getSettings.ts
 *
 * Response: UserSetting array
 */
class GetSettings extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("https://wpa.chat.zalo.me/api/setting/me");
    }

    /**
     * Get my account settings
     *
     * @throws ZaloApiError
     * @return mixed
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
