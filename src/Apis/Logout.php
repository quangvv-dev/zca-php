<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/logout.ts
 *
 * Response: array{status: bool}
 */
class Logout extends BaseApi
{
    /**
     * @var string
     */
    private $logoutTokenURL = "https://id.zalo.me/jr/logout-token";
    /**
     * @var string
     */
    private $logoutURL = "https://id.zalo.me/account/logout";

    /**
     * Logout of the current session
     *
     * @throws ZaloApiError
     */
    public function __invoke(): array
    {
        $tokenResponse = $this->utils->request($this->logoutTokenURL, [
            "method" => "POST",
            "body" => [
                "continue" => "https://chat.zalo.me/",
            ],
        ]);
        if (!Utils::isOk($tokenResponse)) throw new ZaloApiError("Failed to get logout token: " . $tokenResponse->getReasonPhrase());

        $tokenData = Utils::responseJson($tokenResponse);
        $token = $tokenData["data"]["token"] ?? null;
        if (($tokenData["error_code"] ?? null) !== 0 || !$token)
            throw new ZaloApiError($tokenData["error_message"] ?? "Failed to get logout token", $tokenData["error_code"] ?? null);

        $logoutResponse = $this->utils->request($this->logoutURL, [
            "method" => "POST",
            "body" => [
                "token" => $token,
            ],
        ]);
        if (!Utils::isOk($logoutResponse)) throw new ZaloApiError("Failed to logout: " . $logoutResponse->getReasonPhrase());

        // The response is an HTML page that loads each `setsid` URL in an iframe to expire the session cookies on its domain
        $html = (string) $logoutResponse->getBody();
        if (!preg_match('/listSetSidUrls\s*:\s*(\[[^\]]*\])/', $html, $setSidMatch)) throw new ZaloApiError("Failed to logout: setsid urls not found");

        $setSidURLs = Utils::jsonDecode($setSidMatch[1]);
        foreach ($setSidURLs as $url) {
            $this->utils->request($url, [
                "method" => "GET",
                "headers" => [
                    "Referer" => "https://id.zalo.me/",
                ],
            ]);
        }

        $this->api->listener->stop();

        return [
            "status" => true,
        ];
    }
}
