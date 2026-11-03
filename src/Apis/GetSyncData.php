<?php

namespace Zca\Apis;

use Psr\Http\Message\ResponseInterface;
use Zca\Cookie\CookieJar;
use Zca\Errors\ZaloApiError;
use Zca\Utils;

/**
 * Port of src/apis/getSyncData.ts (zca-js PR #269)
 *
 * Response (SyncDataResponse):
 *  - ["success" => true, "data" => ["file_path" => string, "encrypted_key" => string, "metadata" => [file_name, file_size, checksum_code, from_seq_id, is_full_transfer]]]
 *  - ["success" => true, "stream" => ["stream" => \Psr\Http\Message\StreamInterface, "metadata" => [..., "encrypted_key"], "response" => [status, statusText, url, contentType, contentLength]]] when `returnStream` is true
 *  - ["success" => false, "error" => string]
 */
class GetSyncData extends BaseApi
{
    /**
     * Downloads a file stream from a URL with proper authentication
     *
     * @throws ZaloApiError If download fails
     */
    private function downloadFileStream(string $url): ResponseInterface
    {
        if (!$this->ctx->cookie) $this->ctx->cookie = new CookieJar();
        $cookie = $this->ctx->cookie;

        $manualHeader = [
            "Cache-Control" => "no-store, must-revalidate",
            "Pragma" => "no-cache",
            "Accept-Encoding" => "gzip, deflate, br",
            "Accept-Language" => "en-US",
            "User-Agent" => $this->ctx->userAgent ?: "",
            "cookie" => $cookie->getCookieString("https://chat.zalo.me/"),
            "Expires" => "0",
            "Sec-Fetch-Dest" => "empty",
            "Sec-Fetch-Mode" => "no-cors",
            "Sec-Fetch-Site" => "none",
            "Connection" => "keep-alive",
        ];

        $response = $this->utils->request($url, [
            "method" => "GET",
            "headers" => $manualHeader,
            "stream" => true,
        ], true);

        if (!Utils::isOk($response)) {
            throw new ZaloApiError("Failed to download file: HTTP " . $response->getStatusCode());
        }

        return $response;
    }

    /**
     * Downloads a file from a URL with proper authentication
     *
     * @throws ZaloApiError If download fails
     */
    private function downloadFile(string $url): string
    {
        $response = $this->downloadFileStream($url);

        return (string) $response->getBody();
    }

    /**
     * Download sync data from Zalo's mobile sync and save as encrypted .db.crypt file
     *
     * @param array{url: string, encrypted_key: string, file_name: string, file_size: int, checksum_code: string, from_seq_id: int, is_full_transfer: int, returnStream?: bool, savePath?: string} $params
     *        usually the `data` of a SyncEventType::SYNCMSG_INFO `sync_event`
     *
     * @return array The saved file path or stream (SyncDataResponse, see class docblock)
     */
    public function __invoke(array $params): array
    {
        $url = $params["url"] ?? null;
        $encrypted_key = $params["encrypted_key"] ?? null;
        $file_name = $params["file_name"] ?? null;
        $file_size = $params["file_size"] ?? null;
        $checksum_code = $params["checksum_code"] ?? null;
        $from_seq_id = $params["from_seq_id"] ?? null;
        $is_full_transfer = $params["is_full_transfer"] ?? null;
        $returnStream = $params["returnStream"] ?? false;
        $savePath = $params["savePath"] ?? "./";

        try {
            if ($returnStream) {
                $response = $this->downloadFileStream($url);

                $syncDataStream = [
                    "stream" => $response->getBody(),
                    "metadata" => [
                        "file_name" => $file_name,
                        "file_size" => $file_size,
                        "checksum_code" => $checksum_code,
                        "from_seq_id" => $from_seq_id,
                        "is_full_transfer" => $is_full_transfer,
                        "encrypted_key" => $encrypted_key,
                    ],
                    "response" => [
                        "status" => $response->getStatusCode(),
                        "statusText" => $response->getReasonPhrase(),
                        "url" => $url,
                        "contentType" => $response->hasHeader("content-type") ? $response->getHeaderLine("content-type") : null,
                        "contentLength" => $response->hasHeader("content-length") ? $response->getHeaderLine("content-length") : null,
                    ],
                ];

                return [
                    "success" => true,
                    "stream" => $syncDataStream,
                ];
            }

            $encryptedFileData = $this->downloadFile($url);

            $fileName = substr_compare($file_name, ".db.crypt", -strlen(".db.crypt")) === 0 ? $file_name : "{$file_name}.db.crypt";
            $fullPath = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . $fileName;

            $dir = dirname($fullPath);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($fullPath, $encryptedFileData);

            $syncData = [
                "file_path" => $fullPath,
                "encrypted_key" => $encrypted_key,
                "metadata" => [
                    "file_name" => $fileName,
                    "file_size" => $file_size,
                    "checksum_code" => $checksum_code,
                    "from_seq_id" => $from_seq_id,
                    "is_full_transfer" => $is_full_transfer,
                ],
            ];

            return [
                "success" => true,
                "data" => $syncData,
            ];
        } catch (\Throwable $error) {
            return [
                "success" => false,
                "error" => $error->getMessage() ?: "Unknown error occurred",
            ];
        }
    }
}
