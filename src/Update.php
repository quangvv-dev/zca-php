<?php

namespace Zca;

class Update
{
    public const VERSION = "2.2.0";
    public const NPM_REGISTRY = "https://registry.npmjs.org/zca-js";

    /**
     * Check if the upstream zca-js package (which this library is ported from) has a new version.
     */
    public static function checkUpdate(Context $ctx): void
    {
        if (!$ctx->options["checkUpdate"]) return;

        try {
            $client = $ctx->options["httpClient"] ?? new \GuzzleHttp\Client();
            $options = ["http_errors" => false, "timeout" => 10];
            if (!empty($ctx->options["proxy"])) $options["proxy"] = $ctx->options["proxy"];
            $response = $client->request("GET", self::NPM_REGISTRY, $options);
        } catch (\Throwable) {
            return;
        }
        if (!Utils::isOk($response)) return;

        try {
            $data = Utils::responseJson($response);
        } catch (\Throwable) {
            return;
        }
        if (!$data) return;

        $latestVersion = $data["dist-tags"]["latest"];
        if (version_compare(self::VERSION, $latestVersion) === -1) {
            Logger::of($ctx)->info("A new version of zca-js is available: {$latestVersion}");
        } else {
            Logger::of($ctx)->info("zca-js is up to date");
        }
    }
}
