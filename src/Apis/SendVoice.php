<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendVoice.ts
 *
 * Response: array{msgId: string}
 */
class SendVoice extends BaseApi
{
    /** @var array<int, string> */
    private $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/forward"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/group/forward"),
        ];
    }

    /**
     * Send a voice to a User - Group
     *
     * @param array{voiceUrl: string, ttl?: int} $options voice options (ttl: Time to live in milliseconds (default: 0))
     * @param string $threadId ID of the user or group to send the voice to
     * @param int $type Type of thread, default user
     *
     * @throws ZaloApiError
     * @return mixed
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User)
    {
        $fileSize = null;
        $clientId = (string) Utils::now();

        try {
            $headResponse = $this->utils->request($options["voiceUrl"], ["method" => "HEAD"], true);
            if (Utils::isOk($headResponse)) {
                $fileSize = (int) ($headResponse->getHeaderLine("content-length") ?: "0");
            }
        } catch (\Throwable $error) {
            throw new ZaloApiError("Unable to get voice content: " . $error->getMessage());
        }

        $params = $type === ThreadType::User
            ? [
                "toId" => $threadId,
                "ttl" => $options["ttl"] ?? 0,
                "zsource" => -1,
                "msgType" => 3,
                "clientId" => $clientId,
                "msgInfo" => Utils::json([
                    "voiceUrl" => $options["voiceUrl"],
                    "m4aUrl" => $options["voiceUrl"],
                    "fileSize" => $fileSize ?? 0,
                ]),
                "imei" => $this->ctx->imei,
            ]
            : [
                "grid" => $threadId,
                "visibility" => 0,
                "ttl" => $options["ttl"] ?? 0,
                "zsource" => -1,
                "msgType" => 3,
                "clientId" => $clientId,
                "msgInfo" => Utils::json([
                    "voiceUrl" => $options["voiceUrl"],
                    "m4aUrl" => $options["voiceUrl"],
                    "fileSize" => $fileSize ?? 0,
                ]),
                "imei" => $this->ctx->imei,
            ];

        if ($type !== ThreadType::User && $type !== ThreadType::Group) {
            throw new ZaloApiError("Thread type is invalid");
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
}
