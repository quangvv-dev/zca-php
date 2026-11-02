<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getFriendOnlines.ts
 *
 * Response: array{predefine: string[], ownerStatus: string, onlines: array<array{userId: string, status: string}>}
 */
class GetFriendOnlines extends BaseApi
{
    /**
     * @var string
     */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["profile"][0]}/api/social/friend/onlines");
    }

    /**
     * Get friend onlines?
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke()
    {
        $params = [
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->utils->makeURL($this->serviceURL, ["params" => $encryptedParams]), [
            "method" => "GET",
        ]);

        return $this->utils->resolve($response, function (array $result) {
            $data = $result["data"];
            $arrayIsListFunction = function (array $array): bool {
                if (function_exists('array_is_list')) {
                    return array_is_list($array);
                }
                if ($array === []) {
                    return true;
                }
                $current_key = 0;
                foreach ($array as $key => $noop) {
                    if ($key !== $current_key) {
                        return false;
                    }
                    ++$current_key;
                }
                return true;
            };

            if (is_array($data["onlines"] ?? null) && $arrayIsListFunction($data["onlines"])) {
                foreach ($data["onlines"] as &$online) {
                    if (is_string($online["status"])) {
                        $parsed = Utils::jsonDecode($online["status"]);
                        if ($parsed && is_string($parsed["status"] ?? null)) {
                            $online["status"] = $parsed["status"];
                        }
                    }
                }
                unset($online);
            }

            return $data;
        });
    }
}
