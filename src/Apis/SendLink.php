<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendLink.ts
 *
 * Response: array{msgId: string}
 */
class SendLink extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message/link", [
                "nretry" => 0,
            ]),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group/sendlink", [
                "nretry" => 0,
            ]),
        ];
    }

    /**
     * Send link
     *
     * @param array{msg?: string, link: string, ttl?: int} $options Link and ttl
     * @param string $threadId Thread ID
     * @param int $type Thread type
     *
     * @throws ZaloApiError
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User): mixed
    {
        $res = $this->api->parseLink($options["link"]);

        $msg = $options["msg"] ?? null;
        $params = [
            "msg" =>
                $msg !== null && $msg !== "" && self::jsTrim($msg) !== ""
                    ? (str_contains($msg, $options["link"])
                        ? $msg
                        : $msg . " " . $options["link"])
                    : $options["link"],
            "href" => $res["data"]["href"],
            "src" => $res["data"]["src"],
            "title" => $res["data"]["title"],
            "desc" => $res["data"]["desc"],
            "thumb" => $res["data"]["thumb"],
            "type" => 2, // 0
            "media" => Utils::json($res["data"]["media"]),
            "ttl" => $options["ttl"] ?? 0,
            "clientId" => Utils::now(),
        ];

        if ($type == ThreadType::Group) {
            $params["grid"] = $threadId;
            $params["imei"] = $this->ctx->imei;
            // params.mentionInfo = "[]"; @TODO: implement this
        } else {
            $params["toId"] = $threadId;
            $params["mentionInfo"] = "";
        }

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt params");

        $response = $this->utils->request($this->serviceURL[$type], [
            "method" => "POST",
            "body" => [
                "params" => $encryptedParams,
            ],
        ]);

        return $this->utils->resolve($response);
    }

    /**
     * JS `String.prototype.trim()` (also strips Unicode whitespace)
     */
    private static function jsTrim(string $s): string
    {
        return preg_replace('/^[\s\p{Zs}\x{FEFF}\x{2028}\x{2029}]+|[\s\p{Zs}\x{FEFF}\x{2028}\x{2029}]+$/u', "", $s) ?? trim($s);
    }
}
