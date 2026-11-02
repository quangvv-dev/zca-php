<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Utils;

/**
 * Port of src/apis/sendVideo.ts
 *
 * Response: array{msgId: int}
 */
class SendVideo extends BaseApi
{
    /** @var array<int, string> */
    private array $serviceURL;

    protected function init(): void
    {
        $this->serviceURL = [
            ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/message/forward"),
            ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["file"][0]}/api/group/forward"),
        ];
    }

    /**
     * Send a video to a User - Group
     *
     * @param array{msg?: string, videoUrl: string, thumbnailUrl: string, duration?: int, width?: int, height?: int, ttl?: int} $options send video options
     *   - msg: Optional message to send along with the video
     *   - videoUrl: URL of the video
     *   - thumbnailUrl: URL of the thumbnail
     *   - duration: Video duration in milliseconds || Eg: video duration: 5.5s => 5.5 * 1000 = 5500
     *   - width: Width of the video
     *   - height: Height of the video
     *   - ttl: Time to live in milliseconds (default: 0)
     * @param string $threadId ID of the user or group to send the video to
     * @param int $type Type of thread (USER or GROUP)
     *
     * @throws ZaloApiError
     *
     * @example Example Video Resolutions:
     *   - **Standard Videos**:
     *       - 3840x2160 (4K UHD): Width 3840px, Height 2160px.
     *       - 1920x1080 (Full HD): Width 1920px, Height 1080px.
     *       - 1280x720 (HD): Width 1280px, Height 720px.
     *   - **Document-Oriented Videos** (Portrait):
     *       - 3840x2160 (4K UHD): Width 3840px, Height 2160px.
     *       - 720x1280 (HD): Width 720px, Height 1280px.
     *       - 1440x2560 (2K): Width 1440px, Height 2560px.
     */
    public function __invoke(array $options, string $threadId, int $type = ThreadType::User): mixed
    {
        $fileSize = 0;
        $clientId = Utils::now();

        try {
            $headResponse = $this->utils->request($options["videoUrl"], ["method" => "HEAD"], true);
            if (Utils::isOk($headResponse)) {
                $fileSize = (int) ($headResponse->getHeaderLine("content-length") ?: "0");
            }
        } catch (\Throwable $error) {
            throw new ZaloApiError("Unable to get video content: " . $error->getMessage());
        }

        $params = $type === ThreadType::User
            ? [
                "toId" => $threadId,
                "clientId" => (string) $clientId,
                "ttl" => $options["ttl"] ?? 0,
                "zsource" => 704,
                "msgType" => 5,
                "msgInfo" => Utils::json([
                    "videoUrl" => $options["videoUrl"],
                    "thumbUrl" => $options["thumbnailUrl"],
                    "duration" => $options["duration"] ?? 0,
                    "width" => $options["width"] ?? 1280,
                    "height" => $options["height"] ?? 720,
                    "fileSize" => $fileSize,
                    "properties" => [
                        "color" => -1,
                        "size" => -1,
                        "type" => 1003,
                        "subType" => 0,
                        "ext" => [
                            "sSrcType" => -1,
                            "sSrcStr" => "",
                            "msg_warning_type" => 0,
                        ],
                    ],
                    "title" => $options["msg"] ?? "",
                ]),
                "imei" => $this->ctx->imei,
            ]
            : [
                "grid" => $threadId,
                "visibility" => 0,
                "clientId" => (string) $clientId,
                "ttl" => $options["ttl"] ?? 0,
                "zsource" => 704,
                "msgType" => 5,
                "msgInfo" => Utils::json([
                    "videoUrl" => $options["videoUrl"],
                    "thumbUrl" => $options["thumbnailUrl"],
                    "duration" => $options["duration"] ?? 0,
                    "width" => $options["width"] ?? 1280,
                    "height" => $options["height"] ?? 720,
                    "fileSize" => $fileSize,
                    "properties" => [
                        "color" => -1,
                        "size" => -1,
                        "type" => 1003,
                        "subType" => 0,
                        "ext" => [
                            "sSrcType" => -1,
                            "sSrcStr" => "",
                            "msg_warning_type" => 0,
                        ],
                    ],
                    "title" => $options["msg"] ?? "",
                ]),
                "imei" => $this->ctx->imei,
            ];

        // @TODO: later
        // if (typeof message !== "string" && message.mention) {
        //     params.mentionInfo = message.mention;
        // }
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
