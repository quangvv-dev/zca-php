<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;
use Zca\Models\TextStyle;
use Zca\Models\ThreadType;
use Zca\Models\Urgency;
use Zca\Utils;

/**
 * Port of src/apis/sendMessage.ts
 *
 * Response: array{message: array{msgId: int}|null, attachment: array<int, array{msgId: int}>}
 *
 * MessageContent: [
 *   "msg" => string,                 Text content of the message
 *   "styles" => ?array,              Text styles: list of ["start" => int, "len" => int, "st" => TextStyle::*, "indentSize" => ?int]
 *   "urgency" => ?int,               Urgency::*
 *   "quote" => ?array,               Quoted message: ["content", "msgType", "propertyExt", "uidFrom", "msgId", "cliMsgId", "ts", "ttl"] (e.g. $message->data)
 *   "mentions" => ?array,            list of ["pos" => int, "uid" => string, "len" => int]
 *   "attachments" => string|array,   AttachmentSource or list of AttachmentSource
 *   "ttl" => ?int,                   Time to live in milliseconds
 * ]
 */
class SendMessage extends BaseApi
{
    private const ATTACHMENT_URL_TYPE = [
        "image" => "photo_original/send?",
        "gif" => "gif?",
        "video" => "asyncfile/msg?",
        "others" => "asyncfile/msg?",
    ];

    /** @var array{message: array<int, string>, attachment: array<int, string>} */
    private $serviceURLs;
    /** @var array<string, mixed> */
    private $sharefile;

    protected function init(): void
    {
        $this->serviceURLs = [
            "message" => [
                ThreadType::User => $this->utils->makeURL("{$this->api->zpwServiceMap["chat"][0]}/api/message", [
                    "nretry" => 0,
                ]),
                ThreadType::Group => $this->utils->makeURL("{$this->api->zpwServiceMap["group"][0]}/api/group", [
                    "nretry" => 0,
                ]),
            ],
            "attachment" => [
                ThreadType::User => "{$this->api->zpwServiceMap["file"][0]}/api/message/",
                ThreadType::Group => "{$this->api->zpwServiceMap["file"][0]}/api/group/",
            ],
        ];

        $this->sharefile = $this->ctx->settings["features"]["sharefile"];
    }

    /**
     * @return mixed
     */
    private static function prepareQMSGAttach(array $quote)
    {
        $quoteData = $quote;
        if (is_string($quoteData["content"])) return $quoteData["propertyExt"] ?? null;
        if ($quoteData["msgType"] == "chat.todo")
            return [
                "properties" => [
                    "color" => 0,
                    "size" => 0,
                    "type" => 0,
                    "subType" => 0,
                    "ext" => '{"shouldParseLinkOrContact":0}',
                ],
            ];

        return array_merge($quoteData["content"], [
            "thumbUrl" => $quoteData["content"]["thumb"] ?? null,
            "oriUrl" => $quoteData["content"]["href"] ?? null,
            "normalUrl" => $quoteData["content"]["href"] ?? null,
        ]);
    }

    /**
     * @return mixed
     */
    private static function prepareQMSG(array $quote)
    {
        $quoteData = $quote;
        if ($quoteData["msgType"] == "chat.todo" && is_array($quoteData["content"]) && is_string($quoteData["content"]["params"] ?? null)) {
            return Utils::jsonDecode($quoteData["content"]["params"])["item"]["content"];
        }

        return "";
    }

    /**
     * String length in UTF-16 code units, like JS `.length`
     */
    private static function jsLength(string $str): int
    {
        return intdiv(strlen(mb_convert_encoding($str, "UTF-16LE", "UTF-8")), 2);
    }

    private function isExceedMaxFile(int $totalFile): bool
    {
        return $totalFile > $this->sharefile["max_file"];
    }

    private function isExceedMaxFileSize(int $fileSize): bool
    {
        return $fileSize > $this->sharefile["max_size_share_file_v3"] * 1024 * 1024;
    }

    private function getGroupLayoutId(): int
    {
        return Utils::now();
    }

    /**
     * @param array{url: string, body?: mixed, headers?: array<string, string>}|array<int, array{url: string, body?: mixed, headers?: array<string, string>}> $data
     * @return array<int, array{msgId: int}>
     */
    private function send(array $data): array
    {
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
        if (!$arrayIsListFunction($data)) $data = [$data];

        $results = [];

        foreach ($data as $each) {
            $response = $this->utils->request($each["url"], [
                "method" => "POST",
                "body" => $each["body"] ?? null,
                "headers" => $each["headers"] ?? [],
            ]);

            $results[] = Utils::resolveResponse($this->ctx, $response);
        }

        return $results;
    }

    /**
     * @param string|mixed[] $source
     */
    private function upthumb($source, string $url): array
    {
        $buffer = is_string($source) ? file_get_contents($source) : $source["data"];
        $formData = Utils::formData([
            [
                "name" => "fileContent",
                "contents" => $buffer,
                "filename" => "blob",
                "contentType" => "image/png",
            ],
        ]);

        $params = [
            "clientId" => Utils::now(),
            "imei" => $this->ctx->imei,
        ];

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

        $response = $this->utils->request(
            $this->utils->makeURL($url . "upthumb?", [
                "params" => $encryptedParams,
            ]),
            [
                "method" => "POST",
                "headers" => $formData["headers"],
                "body" => $formData["body"],
            ]
        );

        return Utils::resolveResponse($this->ctx, $response);
    }

    /**
     * @return array{mentionsFinal: array, msgFinal: string}
     */
    private function handleMentions(int $type, string $msg, ?array $mentions = null): array
    {
        $totalMentionLen = 0;
        $mentionsFinal = [];
        if (is_array($mentions) && $type == ThreadType::Group) {
            foreach ($mentions as $m) {
                if (!(($m["pos"] ?? -1) >= 0 && !empty($m["uid"]) && ($m["len"] ?? 0) > 0)) continue;

                $totalMentionLen += $m["len"];
                $mentionsFinal[] = [
                    "pos" => $m["pos"],
                    "uid" => $m["uid"],
                    "len" => $m["len"],
                    "type" => $m["uid"] == "-1" ? 1 : 0,
                ];
            }
        }

        if ($totalMentionLen > self::jsLength($msg)) {
            throw new ZaloApiError("Invalid mentions: total mention characters exceed message length");
        }

        return [
            "mentionsFinal" => $mentionsFinal,
            "msgFinal" => $msg,
        ];
    }

    private function handleStyles(array &$params, ?array $styles = null): void
    {
        if ($styles)
            $params["textProperties"] = Utils::json([
                "styles" => array_map(function ($e) {
                    $styleFinal = $e;
                    $styleFinal["indentSize"] = null;
                    $styleFinal["st"] =
                        $e["st"] == TextStyle::Indent
                            ? str_replace("$", ($e["indentSize"] ?? 1) . "0", TextStyle::Indent)
                            : $e["st"];

                    Utils::removeUndefinedKeys($styleFinal);

                    return $styleFinal;
                }, $styles),
                "ver" => 0,
            ]);
    }

    private function handleUrgency(array &$params, ?int $urgency = null): void
    {
        if ($urgency == Urgency::Important || $urgency == Urgency::Urgent) {
            $params["metaData"] = ["urgency" => $urgency];
        }
    }

    /**
     * @return array{url: string, body: array<string, string>}
     */
    private function handleMessage(array $message, string $threadId, int $type): array
    {
        $msg = $message["msg"] ?? null;
        $styles = $message["styles"] ?? null;
        $urgency = $message["urgency"] ?? null;
        $mentions = $message["mentions"] ?? null;
        $quote = $message["quote"] ?? null;
        $ttl = $message["ttl"] ?? null;

        if (!$msg || strlen($msg) == 0) throw new ZaloApiError("Missing message content");
        $isGroupMessage = $type == ThreadType::Group;

        ["mentionsFinal" => $mentionsFinal, "msgFinal" => $msgFinal] = $this->handleMentions($type, $msg, $mentions);
        $msg = $msgFinal;

        if ($quote) {
            if (!is_string($quote["content"]) && $quote["msgType"] == "webchat") {
                throw new ZaloApiError("This kind of `webchat` quote type is not available");
            }

            if ($quote["msgType"] == "group.poll") {
                throw new ZaloApiError("The `group.poll` quote type is not available");
            }
        }

        $isMentionsValid = count($mentionsFinal) > 0 && $isGroupMessage;
        if ($quote) {
            $qmsgAttach = $isGroupMessage ? self::prepareQMSGAttach($quote) : null;
            $params = [
                "toid" => $isGroupMessage ? null : $threadId,
                "grid" => $isGroupMessage ? $threadId : null,
                "message" => $msg,
                "clientId" => Utils::now(),
                "mentionInfo" => $isMentionsValid ? Utils::json($mentionsFinal) : null,
                "qmsgOwner" => $quote["uidFrom"],
                "qmsgId" => $quote["msgId"],
                "qmsgCliId" => $quote["cliMsgId"],
                "qmsgType" => Utils::getClientMessageType($quote["msgType"]),
                "qmsgTs" => $quote["ts"],
                "qmsg" => is_string($quote["content"]) ? $quote["content"] : self::prepareQMSG($quote),
                "imei" => $isGroupMessage ? null : $this->ctx->imei,
                "visibility" => $isGroupMessage ? 0 : null,
                // JSON.stringify(undefined) is undefined, so the key is dropped
                "qmsgAttach" => $isGroupMessage && $qmsgAttach !== null ? Utils::json($qmsgAttach) : null,
                "qmsgTTL" => $quote["ttl"] ?? null,
                "ttl" => $ttl ?? 0,
            ];
        } else {
            $params = [
                "message" => $msg,
                "clientId" => Utils::now(),
                "mentionInfo" => $isMentionsValid ? Utils::json($mentionsFinal) : null,
                "imei" => $isGroupMessage ? null : $this->ctx->imei,
                "ttl" => $ttl ?? 0,
                "visibility" => $isGroupMessage ? 0 : null,
                "toid" => $isGroupMessage ? null : $threadId,
                "grid" => $isGroupMessage ? $threadId : null,
            ];
        }

        $this->handleStyles($params, $styles);
        $this->handleUrgency($params, $urgency);

        Utils::removeUndefinedKeys($params);

        $encryptedParams = $this->utils->encodeAES(Utils::json($params));
        if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");
        if ($quote) {
            $finalServiceUrl = Utils::appendPath($this->serviceURLs["message"][$type], "/quote");
        } else {
            $finalServiceUrl = Utils::appendPath(
                $this->serviceURLs["message"][$type],
                "/" . ($isGroupMessage ? (!empty($params["mentionInfo"]) ? "mention" : "sendmsg") : "sms")
            );
        }

        return [
            "url" => $finalServiceUrl,
            "body" => ["params" => $encryptedParams],
        ];
    }

    /**
     * @return array<int, array{url: string, body: mixed, headers: array<string, string>}>
     */
    private function handleAttachment(array $message, string $threadId, int $type): array
    {
        $msg = $message["msg"] ?? "";
        $attachments = $message["attachments"] ?? null;
        $mentions = $message["mentions"] ?? null;
        $quote = $message["quote"] ?? null;
        $ttl = $message["ttl"] ?? null;
        $urgency = $message["urgency"] ?? null;

        if (!$attachments) throw new ZaloApiError("Missing attachments");
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
        if (!is_array($attachments) || !$arrayIsListFunction($attachments)) $attachments = [$attachments];
        if (count($attachments) == 0) throw new ZaloApiError("Missing attachments");
        $firstSource = $attachments[0];
        $isFilePath = is_string($firstSource);

        $firstExtFile = Utils::getFileExtension($isFilePath ? $firstSource : $firstSource["filename"]);
        $isSingleFile = count($attachments) == 1;
        $isGroupMessage = $type == ThreadType::Group;

        $canBeDesc = $isSingleFile && in_array($firstExtFile, ["jpg", "jpeg", "png", "webp"], true);

        $gifFiles = array_values(array_filter($attachments, function ($e) {
            return Utils::getFileExtension(is_string($e) ? $e : $e["filename"]) == "gif";
        }));
        $attachments = array_values(array_filter($attachments, function ($e) {
            return Utils::getFileExtension(is_string($e) ? $e : $e["filename"]) != "gif";
        }));

        $uploadAttachment = count($attachments) == 0 ? [] : $this->api->uploadAttachment($attachments, $threadId, $type);

        $attachmentsData = [];
        $indexInGroupLayout = 0;

        $groupLayoutId = (string) $this->getGroupLayoutId();

        ["mentionsFinal" => $mentionsFinal, "msgFinal" => $msgFinal] = $this->handleMentions($type, $msg, $mentions);
        $msg = $msgFinal;

        $isMentionsValid = count($mentionsFinal) > 0 && $isGroupMessage && count($attachments) == 1;

        $isMultiFile = count($attachments) > 1;
        $clientId = Utils::now();
        foreach ($uploadAttachment as $attachment) {
            $data = null;
            switch ($attachment["fileType"]) {
                case "image": {
                    $data = [
                        "fileType" => $attachment["fileType"],
                        "params" => [
                            "photoId" => $attachment["photoId"],
                            "clientId" => (string) ($clientId++),
                            "desc" => $msg,
                            "width" => $attachment["width"],
                            "height" => $attachment["height"],
                            "toid" => $isGroupMessage ? null : (string) $threadId,
                            "grid" => $isGroupMessage ? (string) $threadId : null,
                            "rawUrl" => $attachment["normalUrl"],
                            "hdUrl" => $attachment["hdUrl"],
                            "thumbUrl" => $attachment["thumbUrl"],
                            "oriUrl" => $isGroupMessage ? $attachment["normalUrl"] : null,
                            "normalUrl" => $isGroupMessage ? null : $attachment["normalUrl"],
                            "hdSize" => (string) $attachment["totalSize"],
                            "zsource" => -1,
                            "ttl" => $ttl ?? 0,
                            "jcp" => '{"convertible":"jxl"}',

                            "groupLayoutId" => $isMultiFile ? $groupLayoutId : null,
                            "isGroupLayout" => $isMultiFile ? 1 : null,
                            "idInGroup" => $isMultiFile ? $indexInGroupLayout++ : null,
                            "totalItemInGroup" => $isMultiFile ? count($uploadAttachment) : null,
                            "extMsgProp" => $isMultiFile ? "{\"groupMediaMsg\":{\"groupLayoutId\":\"{$groupLayoutId}\"}}" : null,

                            "mentionInfo" =>
                                $isMentionsValid && $canBeDesc && !$quote ? Utils::json($mentionsFinal) : null,
                        ],
                        "body" => [],
                    ];
                    break;
                }

                case "video":
                case "others": {
                    $data = [
                        "fileType" => $attachment["fileType"],
                        "params" => [
                            "fileId" => $attachment["fileId"],
                            "checksum" => $attachment["checksum"],
                            "checksumSha" => "",
                            "extention" => Utils::getFileExtension($attachment["fileName"]),
                            "totalSize" => $attachment["totalSize"],
                            "fileName" => $attachment["fileName"],
                            "clientId" => $attachment["clientFileId"],
                            "fType" => 1,
                            "fileCount" => 0,
                            "fdata" => "{}",
                            "toid" => $isGroupMessage ? null : (string) $threadId,
                            "grid" => $isGroupMessage ? (string) $threadId : null,
                            "fileUrl" => $attachment["fileUrl"],
                            "zsource" => -1,
                            "ttl" => $ttl ?? 0,
                        ],
                        "body" => [],
                    ];
                    break;
                }
            }

            $this->handleUrgency($data["params"], $urgency);

            Utils::removeUndefinedKeys($data["params"]);
            $encryptedParams = $this->utils->encodeAES(Utils::json($data["params"]));
            if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

            $data["body"]["params"] = $encryptedParams;
            $attachmentsData[] = $data;
        }

        foreach ($gifFiles as $gif) {
            $isFilePath = is_string($gif);
            $gifData = $isFilePath ? Utils::getGifMetaData($this->ctx, $gif) : array_merge(is_array($gif["metadata"]) ? $gif["metadata"] : iterator_to_array($gif["metadata"]), ["fileName" => $gif["filename"]]);
            if ($this->isExceedMaxFileSize((int) $gifData["totalSize"]))
                throw new ZaloApiError(
                    "File " . ($isFilePath ? Utils::getFileName($gif) : $gif["filename"]) . " size exceed maximum size of {$this->sharefile["max_size_share_file_v3"]}MB"
                );

            $_upthumb = $this->upthumb($gif, $this->serviceURLs["attachment"][ThreadType::User]);

            $formData = Utils::formData([
                [
                    "name" => "chunkContent",
                    "contents" => $isFilePath ? file_get_contents($gif) : $gif["data"],
                    "filename" => $isFilePath ? Utils::getFileName($gif) : $gif["filename"],
                    "contentType" => "application/octet-stream",
                ],
            ]);

            $params = [
                "clientId" => (string) Utils::now(),
                "fileName" => $gifData["fileName"],
                "totalSize" => $gifData["totalSize"],
                "width" => $gifData["width"] ?? null,
                "height" => $gifData["height"] ?? null,
                "msg" => $msg,
                "type" => 1,
                "ttl" => $ttl ?? 0,
                "visibility" => $isGroupMessage ? 0 : null,
                "toid" => $isGroupMessage ? null : $threadId,
                "grid" => $isGroupMessage ? $threadId : null,
                "thumb" => $_upthumb["url"],
                "checksum" => Utils::getMd5LargeFileObject($gif, (int) $gifData["totalSize"])["data"],
                "totalChunk" => 1,
                "chunkId" => 1,
            ];

            $this->handleUrgency($params, $urgency);

            Utils::removeUndefinedKeys($params);
            $encryptedParams = $this->utils->encodeAES(Utils::json($params));
            if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

            $attachmentsData[] = [
                "query" => [
                    "params" => $encryptedParams,
                    "type" => "1",
                ],
                "body" => $formData["body"],
                "headers" => $formData["headers"],
                "fileType" => "gif",
            ];
        }

        $responses = [];

        foreach ($attachmentsData as $data) {
            $responses[] = [
                "url" => $this->utils->makeURL(
                    $this->serviceURLs["attachment"][$type] . self::ATTACHMENT_URL_TYPE[$data["fileType"]],
                    array_merge(
                        [
                            "nretry" => "0",
                        ],
                        $data["query"] ?? []
                    )
                ),
                "body" => $data["body"],
                "headers" => $data["fileType"] == "gif" ? $data["headers"] : [],
            ];
        }

        return $responses;
    }

    /**
     * Send a message to a thread
     *
     * @param array|string $message Message content (string or MessageContent array, see class docblock)
     * @param string $threadId group or user id
     * @param int $type Message type (ThreadType::User or ThreadType::Group)
     *
     * @throws ZaloApiError|\Zca\Errors\ZaloApiMissingImageMetadataGetter
     */
    public function __invoke($message, string $threadId, int $type = ThreadType::User): array
    {
        if (!$message) throw new ZaloApiError("Missing message content");
        if (!$threadId) throw new ZaloApiError("Missing threadId");
        if (is_string($message)) $message = ["msg" => $message];

        $msg = $message["msg"] ?? "";
        $attachments = $message["attachments"] ?? null;
        $mentions = $message["mentions"] ?? null;
        $quote = $message["quote"] ?? null;
        $ttl = $message["ttl"] ?? null;
        $styles = $message["styles"] ?? null;
        $urgency = $message["urgency"] ?? null;
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

        if ($attachments && (!is_array($attachments) || !$arrayIsListFunction($attachments))) {
            $attachments = [$attachments];
        }

        if (!$msg && (!$attachments || count($attachments) == 0))
            throw new ZaloApiError("Missing message content");

        if ($attachments && $this->isExceedMaxFile(count($attachments)))
            throw new ZaloApiError("Exceed maximum file of " . $this->sharefile["max_file"]);

        $responses = [
            "message" => null,
            "attachment" => [],
        ];

        if ($attachments && count($attachments) > 0) {
            $firstExtFile = Utils::getFileExtension(
                is_string($attachments[0]) ? $attachments[0] : $attachments[0]["filename"]
            );
            $isSingleFile = count($attachments) == 1;

            $canBeDesc = $isSingleFile && in_array($firstExtFile, ["jpg", "jpeg", "png", "webp"], true);
            if ((!$canBeDesc && strlen($msg) > 0) || (strlen($msg) > 0 && $quote)) {
                // send message and attachment separately
                $data = $this->handleMessage($message, $threadId, $type);
                $responses["message"] = $this->send($data)[0];
                $msg = "";
                $mentions = null;
            }
            $handledData = $this->handleAttachment(
                [
                    "msg" => $msg,
                    "mentions" => $mentions,
                    "attachments" => $attachments,
                    "quote" => $quote,
                    "ttl" => $ttl,
                    "styles" => $styles,
                    "urgency" => $urgency,
                ],
                $threadId,
                $type
            );
            $responses["attachment"] = $this->send($handledData);
            $msg = "";
        }

        if (strlen($msg) > 0) {
            $handledData = $this->handleMessage($message, $threadId, $type);
            $responses["message"] = $this->send($handledData)[0];
        }

        return $responses;
    }
}
