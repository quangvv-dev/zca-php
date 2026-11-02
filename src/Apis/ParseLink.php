<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/parseLink.ts
 *
 * Response: array{data: array{thumb: string, title: string, desc: string, src: string, href: string, media: array{type: int, count: int, mediaTitle: string, artist: string, streamUrl: string, stream_icon: string}, stream_icon: string}, error_maps: array<string, int>}
 */
class ParseLink extends BaseApi
{
    private string $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/parselink");
    }

    /**
     * Parse link
     *
     * @param string $link link to parse
     *
     * @throws ZaloApiError
     *
     */
    public function __invoke(string $link): mixed
    {
        $params = [
            "link" => $link,
            "version" => 1, // version 0 is not available errorMaps || version 1 is errorMaps (for response)
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
