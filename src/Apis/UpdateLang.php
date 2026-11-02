<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\UpdateLangAvailableLanguages;
use Zca\Utils;

/**
 * Port of src/apis/updateLang.ts
 *
 * Response: ""
 */
class UpdateLang extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/profile/updatelang");
    }

    /**
     * Update language?
     *
     * @param string $language language to update (VI, EN)
     *
     * @note Calling this API alone will not update the user's language.
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(string $language = UpdateLangAvailableLanguages::VI)
    {
        $params = [
            "language" => $language,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response);
    }
}
