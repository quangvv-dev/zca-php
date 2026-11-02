<?php

namespace Zca\Apis;

use React\Promise\Deferred;
use React\Promise\Timer\TimeoutException;
use Zca\Errors\ZaloApiError;
use Zca\Models\ThreadType;
use Zca\Async;
use Zca\Utils;

use function React\Promise\Timer\timeout;

/**
 * Port of src/apis/uploadAttachment.ts
 *
 * Response: list of
 *  - image:  array{fileType: "image", width: int, height: int, totalSize: int, hdSize: int, finished: int|bool, normalUrl: string, hdUrl: string, thumbUrl: string, chunkId: int, photoId: string, clientFileId: int}
 *  - video / others: array{fileType: "video"|"others", finished: int|bool, clientFileId: int, chunkId: int, fileId: string, fileUrl: string, totalSize: int, fileName: string, checksum: string}
 *
 * Videos and other files are only finished when Zalo sends the `file_done` event through the websocket,
 * so the listener (`$api->listener->start()`) must be started to upload them, same as zca-js.
 */
class UploadAttachment extends BaseApi
{
    private const URL_TYPE = [
        "image" => "photo_original/upload",
        "video" => "asyncfile/upload",
        "others" => "asyncfile/upload",
    ];

    /**
     * @var string
     */
    private $serviceURL;
    /** @var array<string, mixed> */
    private $sharefile;

    protected function init(): void
    {
        $this->serviceURL = "{$this->api->zpwServiceMap["file"][0]}/api";
        $this->sharefile = $this->ctx->settings["features"]["sharefile"];
    }

    private function isExceedMaxFile(int $totalFile): bool
    {
        return $totalFile > $this->sharefile["max_file"];
    }

    private function isExceedMaxFileSize(int $fileSize): bool
    {
        return $fileSize > $this->sharefile["max_size_share_file_v3"] * 1024 * 1024;
    }

    private function isExtensionValid(string $ext): bool
    {
        return !in_array($ext, $this->sharefile["restricted_ext_file"], true);
    }

    /**
     * Upload an attachment to a thread
     *
     * @param string|array|array<int, string|array> $sources path to files or attachment sources
     *        (an attachment source is ["data" => binary string, "filename" => "name.ext", "metadata" => ["totalSize" => int, "width" => ?int, "height" => ?int]])
     * @param string $threadId Group or User ID
     * @param int $type Message type (ThreadType::User or ThreadType::Group)
     *
     * @throws ZaloApiError|\Zca\Errors\ZaloApiMissingImageMetadataGetter
     */
    public function __invoke($sources, string $threadId, int $type = ThreadType::User): array
    {
        if (!$sources) throw new ZaloApiError("Missing sources");
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
        if (!is_array($sources) || !$arrayIsListFunction($sources)) $sources = [$sources];
        if (count($sources) == 0) throw new ZaloApiError("Missing sources");
        if ($this->isExceedMaxFile(count($sources))) throw new ZaloApiError("Exceed maximum file of " . $this->sharefile["max_file"]);
        if (!$threadId) throw new ZaloApiError("Missing threadId");

        $chunkSize = $this->ctx->settings["features"]["sharefile"]["chunk_size_file"];
        $isGroupMessage = $type == ThreadType::Group;
        $attachmentsData = [];
        $url = "{$this->serviceURL}/" . ($isGroupMessage ? "group" : "message") . "/";
        $typeParam = $isGroupMessage ? "11" : "2";

        $clientId = Utils::now();
        foreach ($sources as $source) {
            $isFilePath = is_string($source);
            $isBuffer = is_array($source) && is_string($source["data"] ?? null);

            if (!$isFilePath && !$isBuffer) throw new ZaloApiError("Invalid source type");
            if (!$isFilePath && empty($source["filename"])) throw new ZaloApiError("Missing filename");

            if ($isFilePath && !file_exists($source)) throw new ZaloApiError("File not found");

            $extFile = strtolower(Utils::getFileExtension($isFilePath ? $source : $source["filename"]));
            $fileName = $isFilePath ? Utils::getFileName($source) : $source["filename"];

            if ($this->isExtensionValid($extFile) == false)
                throw new ZaloApiError("File extension \"{$extFile}\" is not allowed");

            $data = [
                "filePath" => $isFilePath ? $source : $source["filename"],
                "chunkContent" => [],
                "params" => [],
                "source" => $source,
            ];

            if ($isGroupMessage) $data["params"]["grid"] = $threadId;
            else $data["params"]["toid"] = $threadId;

            switch ($extFile) {
                case "jpg":
                case "jpeg":
                case "png":
                case "webp": {
                    $imageData = $isFilePath ? Utils::getImageMetaData($this->ctx, $source) : array_merge(is_array($source["metadata"]) ? $source["metadata"] : iterator_to_array($source["metadata"]), ["fileName" => $fileName]);
                    if ($this->isExceedMaxFileSize((int) $imageData["totalSize"]))
                        throw new ZaloApiError(
                            "File {$fileName} size exceed maximum size of {$this->sharefile["max_size_share_file_v3"]}MB"
                        );

                    $data["fileData"] = $imageData;
                    $data["fileType"] = "image";

                    $data["params"]["totalChunk"] = (int) ceil($data["fileData"]["totalSize"] / $chunkSize);
                    $data["params"]["fileName"] = $fileName;
                    $data["params"]["clientId"] = $clientId++;
                    $data["params"]["totalSize"] = $imageData["totalSize"];
                    $data["params"]["imei"] = $this->ctx->imei;
                    $data["params"]["isE2EE"] = 0;
                    $data["params"]["jxl"] = 0;
                    $data["params"]["chunkId"] = 1;

                    break;
                }
                case "mp4": {
                    $videoSize = $isFilePath ? Utils::getFileSize($source) : $source["metadata"]["totalSize"];
                    if ($this->isExceedMaxFileSize($videoSize))
                        throw new ZaloApiError(
                            "File {$fileName} size exceed maximum size of {$this->sharefile["max_size_share_file_v3"]}MB"
                        );

                    $data["fileType"] = "video";
                    $data["fileData"] = [
                        "fileName" => $fileName,
                        "totalSize" => $videoSize,
                    ];

                    $data["params"]["totalChunk"] = (int) ceil($data["fileData"]["totalSize"] / $chunkSize);
                    $data["params"]["fileName"] = $fileName;
                    $data["params"]["clientId"] = $clientId++;
                    $data["params"]["totalSize"] = $videoSize;
                    $data["params"]["imei"] = $this->ctx->imei;
                    $data["params"]["isE2EE"] = 0;
                    $data["params"]["jxl"] = 0;
                    $data["params"]["chunkId"] = 1;

                    break;
                }
                default: {
                    $fileSize = $isFilePath ? Utils::getFileSize($source) : $source["metadata"]["totalSize"];
                    if ($this->isExceedMaxFileSize($fileSize))
                        throw new ZaloApiError(
                            "File {$fileName} size exceed maximum size of {$this->sharefile["max_size_share_file_v3"]}MB"
                        );

                    $data["fileType"] = "others";
                    $data["fileData"] = [
                        "fileName" => $fileName,
                        "totalSize" => $fileSize,
                    ];

                    $data["params"]["totalChunk"] = (int) ceil($data["fileData"]["totalSize"] / $chunkSize);
                    $data["params"]["fileName"] = $fileName;
                    $data["params"]["clientId"] = $clientId++;
                    $data["params"]["totalSize"] = $fileSize;
                    $data["params"]["imei"] = $this->ctx->imei;
                    $data["params"]["isE2EE"] = 0;
                    $data["params"]["jxl"] = 0;
                    $data["params"]["chunkId"] = 1;

                    break;
                }
            }

            $fileBuffer = $isFilePath ? file_get_contents($source) : $source["data"];
            for ($i = 0; $i < $data["params"]["totalChunk"]; $i++) {
                $slicedBuffer = (string) substr($fileBuffer, $i * $chunkSize, $chunkSize);
                $data["chunkContent"][$i] = Utils::formData([
                    [
                        "name" => "chunkContent",
                        "contents" => $slicedBuffer,
                        "filename" => $fileName,
                        "contentType" => "application/octet-stream",
                    ],
                ]);
            }
            $attachmentsData[] = $data;
        }

        $results = [];

        for ($atmIndex = 0; $atmIndex < count($attachmentsData); $atmIndex++) {
            $data = $attachmentsData[$atmIndex];
            for ($i = 0; $i < $data["params"]["totalChunk"]; $i++) {
                $encryptedParams = $this->utils->encodeAES(Utils::json($data["params"]));
                if (!$encryptedParams) throw new ZaloApiError("Failed to encrypt message");

                $response = $this->utils->request(
                    $this->utils->makeURL($url . self::URL_TYPE[$data["fileType"]], ["type" => $typeParam, "params" => $encryptedParams]),
                    [
                        "method" => "POST",
                        "headers" => $data["chunkContent"][$i]["headers"],
                        "body" => $data["chunkContent"][$i]["body"],
                    ]
                );

                $resData = Utils::resolveResponse($this->ctx, $response);

                if ($resData && ($resData["fileId"] ?? null) != "-1" && ($resData["photoId"] ?? null) != "-1") {
                    if ($data["fileType"] == "video" || $data["fileType"] == "others") {
                        $wsData = $this->waitForUpload((string) $resData["fileId"]);

                        $results[$atmIndex] = array_merge(
                            ["fileType" => $data["fileType"]],
                            $resData,
                            $wsData,
                            [
                                "totalSize" => $data["fileData"]["totalSize"],
                                "fileName" => $data["fileData"]["fileName"],
                                "checksum" => Utils::getMd5LargeFileObject($data["source"], $data["fileData"]["totalSize"])["data"],
                            ]
                        );
                    }

                    if ($data["fileType"] == "image") {
                        $results[$atmIndex] = [
                            "fileType" => "image",
                            "width" => $data["fileData"]["width"],
                            "height" => $data["fileData"]["height"],
                            "totalSize" => $data["fileData"]["totalSize"],
                            "hdSize" => $data["fileData"]["totalSize"],

                            "finished" => $resData["finished"],
                            "normalUrl" => $resData["normalUrl"],
                            "hdUrl" => $resData["hdUrl"],
                            "thumbUrl" => $resData["thumbUrl"],
                            "chunkId" => $resData["chunkId"],

                            "photoId" => $resData["photoId"],
                            "clientFileId" => $resData["clientFileId"],
                        ];
                    }
                }
                $data["params"]["chunkId"]++;
            }
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Wait for the `file_done` websocket event of an uploaded file (zca-js `ctx.uploadCallbacks`).
     *
     * @return array{fileUrl: string, fileId: string}
     */
    private function waitForUpload(string $fileId): array
    {
        if (!$this->api->listener->isStarted()) {
            throw new ZaloApiError("The listener must be started to upload videos/files (call \$api->listener->start())");
        }

        $deferred = new Deferred();
        $this->ctx->uploadCallbacks->set($fileId, function (array $wsData) use ($deferred) {
            $deferred->resolve($wsData);
        });

        try {
            return Async::await(timeout($deferred->promise(), $this->ctx->options["uploadTimeout"] ?? 300));
        } catch (TimeoutException $exception) {
            $this->ctx->uploadCallbacks->delete($fileId);
            throw new ZaloApiError("Timed out waiting for the upload of file {$fileId} to finish");
        }
    }
}
