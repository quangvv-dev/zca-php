<?php

namespace Zca;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use Zca\Cookie\CookieJar;
use Zca\Errors\ZaloApiError;
use Zca\Errors\ZaloApiMissingImageMetadataGetter;
use Zca\Models\FriendEventType;
use Zca\Models\GroupEventType;

final class Utils
{
    /**
     * JSON.stringify equivalent (no escaping of unicode/slashes).
     */
    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * JSON.parse equivalent, objects are decoded as associative arrays.
     */
    public static function jsonDecode(string $json): mixed
    {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    }

    /**
     * Date.now() equivalent
     */
    public static function now(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public static function hasOwn(mixed $obj, string $key): bool
    {
        return is_array($obj) && array_key_exists($key, $obj);
    }

    /**
     * Get signed key for API requests.
     *
     * @param array<string, mixed> $params
     * @return string MD5 hash
     */
    public static function getSignKey(string $type, array $params): string
    {
        $n = array_map("strval", array_keys($params));
        sort($n, SORT_STRING);

        $a = "zsecure" . $type;
        foreach ($n as $s) $a .= self::jsString($params[$s]);

        return md5($a);
    }

    /**
     * String(value) equivalent for scalar values
     */
    public static function jsString(mixed $value): string
    {
        if ($value === null) return "null";
        if ($value === true) return "true";
        if ($value === false) return "false";
        if (is_array($value)) return array_is_list($value) ? implode(",", array_map([self::class, "jsString"], $value)) : "[object Object]";

        return (string) $value;
    }

    /**
     * @param array<string, string|int|float> $params
     * @param bool $apiVersion automatically add zalo api version to url params
     */
    public static function makeURL(Context $ctx, string $baseURL, array $params = [], bool $apiVersion = true): string
    {
        $url = new Uri($baseURL);
        $query = self::parseQuery($url->getQuery());
        $modified = false;

        foreach ($params as $key => $value) {
            $query[] = [(string) $key, self::jsString($value)];
            $modified = true;
        }

        if ($apiVersion) {
            $keys = array_column($query, 0);
            if (!in_array("zpw_ver", $keys, true)) {
                $query[] = ["zpw_ver", (string) $ctx->API_VERSION];
                $modified = true;
            }
            if (!in_array("zpw_type", $keys, true)) {
                $query[] = ["zpw_type", (string) $ctx->API_TYPE];
                $modified = true;
            }
        }

        if ($url->getPath() === "" && $url->getHost() !== "") $url = $url->withPath("/");
        if (!$modified) return (string) $url;

        return (string) $url->withQuery(self::buildQuery($query));
    }

    /**
     * Append a segment to the pathname of an URL (`url.pathname = url.pathname + suffix`)
     */
    public static function appendPath(string $url, string $suffix): string
    {
        $uri = new Uri($url);

        return (string) $uri->withPath($uri->getPath() . $suffix);
    }

    /**
     * WHATWG URLSearchParams parsing
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function parseQuery(string $query): array
    {
        $result = [];
        if ($query === "") return $result;

        foreach (explode("&", $query) as $pair) {
            if ($pair === "") continue;
            $parts = explode("=", $pair, 2);
            $result[] = [urldecode($parts[0]), urldecode($parts[1] ?? "")];
        }

        return $result;
    }

    /**
     * WHATWG URLSearchParams serializing (application/x-www-form-urlencoded)
     *
     * @param array<int, array{0: string, 1: string}>|array<string, mixed> $params list of pairs or associative array
     */
    public static function buildQuery(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if (is_array($value) && array_is_list($value) && count($value) == 2 && is_int($key)) {
                [$k, $v] = $value;
            } else {
                [$k, $v] = [$key, $value];
            }
            $pairs[] = self::formEncode((string) $k) . "=" . self::formEncode(self::jsString($v));
        }

        return implode("&", $pairs);
    }

    private static function formEncode(string $value): string
    {
        return str_replace("%2A", "*", urlencode($value));
    }

    /**
     * Build a multipart/form-data body, replacement for the `form-data` package.
     *
     * @param array<int, array{name: string, contents: mixed, filename?: string, contentType?: string}> $parts
     * @return array{headers: array<string, string>, body: string}
     */
    public static function formData(array $parts): array
    {
        $boundary = "--------------------------" . substr(str_shuffle(str_repeat("0123456789", 3)), 0, 24);
        $elements = [];
        foreach ($parts as $part) {
            $element = [
                "name" => $part["name"],
                "contents" => $part["contents"],
            ];
            if (isset($part["filename"])) $element["filename"] = $part["filename"];
            if (isset($part["contentType"])) $element["headers"] = ["Content-Type" => $part["contentType"]];
            $elements[] = $element;
        }

        $stream = new MultipartStream($elements, $boundary);

        return [
            "headers" => ["content-type" => "multipart/form-data; boundary=" . $boundary],
            "body" => (string) $stream,
        ];
    }

    /**
     * AES-CBC with zero IV, key is the raw string bytes.
     */
    private static function aesCipherName(string $key, string $mode = "cbc"): string
    {
        $bits = strlen($key) * 8;
        if (!in_array($bits, [128, 192, 256], true)) {
            throw new ZaloApiError("Invalid AES key length: " . strlen($key));
        }

        return "aes-{$bits}-{$mode}";
    }

    public static function decryptResp(string $key, string $data): array|string|null
    {
        $n = null;
        try {
            $n = self::decodeRespAES($key, $data);
            if ($n === null) return null;
            $parsed = self::jsonDecode($n);

            return $parsed;
        } catch (\Throwable) {
            return $n;
        }
    }

    private static function decodeRespAES(string $key, string $data): ?string
    {
        $data = rawurldecode($data);
        $result = openssl_decrypt(base64_decode($data), self::aesCipherName($key), $key, OPENSSL_RAW_DATA, str_repeat("\0", 16));

        return $result === false ? null : $result;
    }

    public static function decodeBase64ToBuffer(string $data): string
    {
        return base64_decode($data);
    }

    public static function decodeUnit8Array(string $data): ?string
    {
        return mb_check_encoding($data, "UTF-8") ? $data : mb_convert_encoding($data, "UTF-8", "UTF-8");
    }

    public static function encodeAES(string $secretKey, string $data, int $t = 0): ?string
    {
        try {
            $key = base64_decode($secretKey);
            $result = openssl_encrypt($data, self::aesCipherName($key), $key, OPENSSL_RAW_DATA, str_repeat("\0", 16));
            if ($result === false) throw new \RuntimeException("encrypt failed");

            return base64_encode($result);
        } catch (\Throwable) {
            return $t < 3 ? self::encodeAES($secretKey, $data, $t + 1) : null;
        }
    }

    public static function decodeAES(string $secretKey, string $data, int $t = 0): ?string
    {
        try {
            $data = rawurldecode($data);
            $key = base64_decode($secretKey);
            $result = openssl_decrypt(base64_decode($data), self::aesCipherName($key), $key, OPENSSL_RAW_DATA, str_repeat("\0", 16));
            if ($result === false) throw new \RuntimeException("decrypt failed");

            return $result;
        } catch (\Throwable) {
            return $t < 3 ? self::decodeAES($secretKey, $data, $t + 1) : null;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getDefaultHeaders(Context $ctx, string $origin = "https://chat.zalo.me"): array
    {
        if (!$ctx->cookie) throw new ZaloApiError("Cookie is not available");
        if (!$ctx->userAgent) throw new ZaloApiError("User agent is not available");

        return [
            "Accept" => "application/json, text/plain, */*",
            "Accept-Encoding" => "gzip, deflate, br, zstd",
            "Accept-Language" => "en-US,en;q=0.9",
            "content-type" => "application/x-www-form-urlencoded",
            "Cookie" => $ctx->cookie->getCookieString($origin),
            "Origin" => "https://chat.zalo.me",
            "Referer" => "https://chat.zalo.me/",
            "User-Agent" => $ctx->userAgent,
        ];
    }

    /**
     * Merge headers case-insensitively, later values win (like fetch Headers).
     *
     * @param array<string, string> ...$headerSets
     * @return array<string, string>
     */
    public static function mergeHeaders(array ...$headerSets): array
    {
        $result = [];
        $names = [];
        foreach ($headerSets as $headers) {
            foreach ($headers as $name => $value) {
                $lower = strtolower($name);
                if (isset($names[$lower])) unset($result[$names[$lower]]);
                $names[$lower] = $name;
                $result[$name] = $value;
            }
        }

        return $result;
    }

    private static ?Client $defaultClient = null;

    private static function getHttpClient(Context $ctx): \GuzzleHttp\ClientInterface
    {
        if (isset($ctx->options["httpClient"]) && $ctx->options["httpClient"] instanceof \GuzzleHttp\ClientInterface) {
            return $ctx->options["httpClient"];
        }

        return self::$defaultClient ??= new Client();
    }

    /**
     * fetch() equivalent used by every API.
     *
     * Options:
     *  - method: string (default GET)
     *  - headers: array<string, string>
     *  - body: string | array<string, mixed> (array is sent as application/x-www-form-urlencoded, like URLSearchParams)
     *  - redirect: "follow" (default) | "manual"
     *
     * @param array{method?: string, headers?: array<string, string>, body?: string|array<string, mixed>|null, redirect?: string}|null $options
     */
    public static function request(Context $ctx, string $url, ?array $options = null, bool $raw = false): ResponseInterface
    {
        if (!$ctx->cookie) $ctx->cookie = new CookieJar();
        $uri = new Uri($url);
        $origin = $uri->getScheme() . "://" . $uri->getAuthority();

        $defaultHeaders = self::getDefaultHeaders($ctx, $origin);
        if (!$raw) {
            if ($options) {
                $options["headers"] = self::mergeHeaders($defaultHeaders, $options["headers"] ?? []);
            } else {
                $options = ["headers" => $defaultHeaders];
            }
        }
        $options ??= [];

        $method = strtoupper($options["method"] ?? "GET");
        $headers = $options["headers"] ?? [];
        $body = $options["body"] ?? null;
        if (is_array($body)) {
            $body = self::buildQuery($body);
            $hasContentType = false;
            foreach ($headers as $name => $_) if (strtolower($name) === "content-type") $hasContentType = true;
            if (!$hasContentType) $headers["content-type"] = "application/x-www-form-urlencoded;charset=UTF-8";
        }

        // let curl negotiate the encodings it actually supports and decode the body for us
        foreach ($headers as $name => $_) if (strtolower($name) === "accept-encoding") unset($headers[$name]);

        $guzzleOptions = [
            "headers" => $headers,
            "http_errors" => false,
            "decode_content" => true,
            "allow_redirects" => ($options["redirect"] ?? "follow") === "manual" ? false : ["max" => 20, "referer" => false],
        ];
        if ($body !== null && $body !== "") $guzzleOptions["body"] = $body;
        if (!empty($ctx->options["proxy"])) $guzzleOptions["proxy"] = $ctx->options["proxy"];
        if (isset($options["timeout"])) $guzzleOptions["timeout"] = $options["timeout"];

        $response = self::getHttpClient($ctx)->request($method, $url, $guzzleOptions);

        $cookieStrings = $response->getHeader("Set-Cookie");
        if (count($cookieStrings) > 0 && !$raw) {
            foreach ($cookieStrings as $cookie) {
                try {
                    $domain = self::getSetCookieDomain($cookie);
                    $ctx->cookie->setCookie($cookie, $domain != "zalo.me" ? "https://" . ($domain ?? "null") : $origin);
                } catch (\Throwable $error) {
                    Logger::of($ctx)->error($error->getMessage());
                }
            }
        }

        $redirectURL = $response->getHeaderLine("Location");

        if ($redirectURL !== "") {
            $redirectURL = (string) UriResolver::resolve($uri, new Uri($redirectURL));
            $redirectOptions = $options;
            $redirectOptions["method"] = "GET";
            unset($redirectOptions["body"]);
            if (!$raw) {
                $redirectOptions["headers"] = self::mergeHeaders($redirectOptions["headers"] ?? [], ["Referer" => "https://id.zalo.me/"]);
            }

            return self::request($ctx, $redirectURL, $redirectOptions);
        }

        return $response;
    }

    private static function getSetCookieDomain(string $setCookie): ?string
    {
        if (preg_match('/;\s*domain\s*=\s*([^;]*)/i', $setCookie, $m)) {
            $domain = strtolower(trim($m[1]));
            if ($domain === "") return null;

            return ltrim($domain, ".");
        }

        return null;
    }

    public static function isOk(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * response.json() equivalent
     */
    public static function responseJson(ResponseInterface $response): mixed
    {
        return self::jsonDecode((string) $response->getBody());
    }

    /**
     * @return array{fileName: string, totalSize: int, width: int, height: int}
     */
    public static function getImageMetaData(Context $ctx, string $filePath): array
    {
        $getter = $ctx->options["imageMetadataGetter"] ?? null;
        if (!$getter) {
            throw new ZaloApiMissingImageMetadataGetter();
        }

        $imageData = $getter($filePath);
        if (!$imageData) {
            throw new ZaloApiError("Failed to get image metadata");
        }

        $parts = explode("/", $filePath);
        $fileName = end($parts);

        return [
            "fileName" => $fileName,
            "totalSize" => $imageData["size"],
            "width" => $imageData["width"],
            "height" => $imageData["height"],
        ];
    }

    public static function getFileSize(string $filePath): int
    {
        $size = @filesize($filePath);
        if ($size === false) throw new ZaloApiError("ENOENT: no such file or directory, stat '{$filePath}'");

        return $size;
    }

    /**
     * @return array{fileName: string, totalSize: int, width: int, height: int}
     */
    public static function getGifMetaData(Context $ctx, string $filePath): array
    {
        $getter = $ctx->options["imageMetadataGetter"] ?? null;
        if (!$getter) {
            throw new ZaloApiMissingImageMetadataGetter();
        }

        $gifData = $getter($filePath);
        if (!$gifData) {
            throw new ZaloApiError("Failed to get gif metadata");
        }

        $fileName = basename($filePath);

        return [
            "fileName" => $fileName,
            "totalSize" => $gifData["size"],
            "width" => $gifData["width"],
            "height" => $gifData["height"],
        ];
    }

    /**
     * Default `imageMetadataGetter` based on getimagesize().
     *
     * @return array{width: int, height: int, size: int}|null
     */
    public static function defaultImageMetadataGetter(string $filePath): ?array
    {
        if (!is_file($filePath)) return null;
        $info = @getimagesize($filePath);
        if ($info === false) return null;

        return [
            "width" => $info[0],
            "height" => $info[1],
            "size" => filesize($filePath),
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     */
    public static function decodeEventData(array $parsed, ?string $cipherKey = null): mixed
    {
        if (!is_string($parsed["data"] ?? null))
            throw new ZaloApiError("Invalid data, expected string but got " . gettype($parsed["data"] ?? null));
        if (!is_int($parsed["encrypt"] ?? null))
            throw new ZaloApiError("Invalid encrypt type, expected number but got " . gettype($parsed["encrypt"] ?? null));
        if ($parsed["encrypt"] < 0 || $parsed["encrypt"] > 3)
            throw new ZaloApiError("Invalid encrypt type, expected 0-3 but got " . $parsed["encrypt"]);

        $rawData = $parsed["data"];
        $encryptType = $parsed["encrypt"];

        if ($encryptType === 0) return self::jsonDecode($rawData);

        $decodedBuffer = self::decodeBase64ToBuffer($encryptType === 1 ? $rawData : rawurldecode($rawData));

        $decryptedBuffer = $decodedBuffer;
        if ($encryptType !== 1) {
            if ($cipherKey && strlen($decodedBuffer) >= 48) {
                $iv = substr($decodedBuffer, 0, 16);
                $additionalData = substr($decodedBuffer, 16, 16);
                $dataSource = substr($decodedBuffer, 32);

                $key = self::decodeBase64ToBuffer($cipherKey);
                $tag = substr($dataSource, -16);
                $cipherText = substr($dataSource, 0, -16);

                $decryptedBuffer = openssl_decrypt($cipherText, self::aesCipherName($key, "gcm"), $key, OPENSSL_RAW_DATA, $iv, $tag, $additionalData);
                if ($decryptedBuffer === false) throw new ZaloApiError("The operation failed for an operation-specific reason");
            } else {
                throw new ZaloApiError("Invalid data length or missing cipher key");
            }
        }

        if ($encryptType === 3) {
            $decompressedBuffer = $decryptedBuffer;
        } else {
            $decompressedBuffer = @zlib_decode($decryptedBuffer);
            if ($decompressedBuffer === false) throw new ZaloApiError("incorrect header check");
        }
        $decodedData = self::decodeUnit8Array($decompressedBuffer);

        if (!$decodedData) return null;

        return self::jsonDecode($decodedData);
    }

    /**
     * @param string|array{data: string, filename: string, metadata: array} $source
     * @return array{currentChunk: int, data: string}
     */
    public static function getMd5LargeFileObject(string|array $source, int $fileSize): array
    {
        $buffer = is_string($source) ? file_get_contents($source) : $source["data"];
        $chunkSize = 2097152; // Read in chunks of 2MB
        $chunks = (int) ceil($fileSize / $chunkSize);
        $ctx = hash_init("md5");

        $currentChunk = 0;
        do {
            $start = $currentChunk * $chunkSize;
            $end = $start + $chunkSize >= $fileSize ? $fileSize : $start + $chunkSize;
            hash_update($ctx, (string) substr($buffer, $start, max(0, $end - $start)));
            $currentChunk++;
        } while ($currentChunk < $chunks);

        return [
            "currentChunk" => $currentChunk,
            "data" => hash_final($ctx),
        ];
    }

    public static function getClientMessageType(string $msgType): int
    {
        if ($msgType === "webchat") return 1;
        if ($msgType === "chat.voice") return 31;
        if ($msgType === "chat.photo") return 32;
        if ($msgType === "chat.sticker") return 36;
        if ($msgType === "chat.doodle") return 37;
        if ($msgType === "chat.recommended") return 38;

        if ($msgType === "chat.link") return 38; // don't know || if (msgType === "chat.link") return 1;
        if ($msgType === "chat.video.msg") return 44; // not sure

        if ($msgType === "share.file") return 46;
        if ($msgType === "chat.gif") return 49;
        if ($msgType === "chat.location.new") return 43;

        return 1;
    }

    public static function strPadLeft(int|string $e, string $t, int $n): string
    {
        $e = "" . $e;
        $a = strlen($e);

        return $a === $n ? $e : ($a > $n ? substr($e, -$n) : str_repeat($t, $n - $a) . $e);
    }

    public static function formatTime(string $format, ?int $timestamp = null): string
    {
        $timestamp ??= self::now();
        $date = (new \DateTimeImmutable("@" . intdiv($timestamp, 1000)))->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        if (str_contains($format, "%H") || str_contains($format, "%d")) {
            $replace = [
                "%H" => $date->format("H"),
                "%M" => $date->format("i"),
                "%S" => $date->format("s"),
                "%d" => $date->format("d"),
                "%m" => $date->format("m"),
                "%Y" => $date->format("Y"),
            ];
            foreach ($replace as $search => $value) {
                $pos = strpos($format, $search);
                if ($pos !== false) $format = substr_replace($format, $value, $pos, strlen($search));
            }

            return $format;
        }

        // Intl.DateTimeFormat("vi-VN") style: HH:mm:ss dd/mm/yyyy
        return $date->format("H:i:s d/m/Y");
    }

    public static function getFullTimeFromMillisecond(int $e): string
    {
        $t = (new \DateTimeImmutable("@" . intdiv($e, 1000)))->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $t->format("H:i d/m/Y");
    }

    public static function getFileExtension(string $e): string
    {
        $base = basename($e);
        $pos = strrpos($base, ".");
        if ($pos === false || $pos === 0) return "";

        return substr($base, $pos + 1);
    }

    public static function getFileName(string $e): string
    {
        return basename($e);
    }

    /**
     * In PHP `null` plays the role of JS `undefined`: keys with null values are removed.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    public static function removeUndefinedKeys(array &$e): array
    {
        foreach ($e as $t => $value) if ($value === null) unset($e[$t]);

        return $e;
    }

    public static function getGroupEventType(string $act): string
    {
        if ($act == "join_request") return GroupEventType::JOIN_REQUEST;
        if ($act == "join") return GroupEventType::JOIN;
        if ($act == "leave") return GroupEventType::LEAVE;
        if ($act == "remove_member") return GroupEventType::REMOVE_MEMBER;
        if ($act == "block_member") return GroupEventType::BLOCK_MEMBER;
        if ($act == "update_setting") return GroupEventType::UPDATE_SETTING;
        if ($act == "update_avatar") return GroupEventType::UPDATE_AVATAR;
        if ($act == "update") return GroupEventType::UPDATE;
        if ($act == "new_link") return GroupEventType::NEW_LINK;
        if ($act == "add_admin") return GroupEventType::ADD_ADMIN;
        if ($act == "remove_admin") return GroupEventType::REMOVE_ADMIN;

        if ($act == "new_pin_topic") return GroupEventType::NEW_PIN_TOPIC;
        if ($act == "update_pin_topic") return GroupEventType::UPDATE_PIN_TOPIC;
        if ($act == "update_topic") return GroupEventType::UPDATE_TOPIC;
        if ($act == "update_board") return GroupEventType::UPDATE_BOARD;
        if ($act == "remove_board") return GroupEventType::REMOVE_BOARD;
        if ($act == "reorder_pin_topic") return GroupEventType::REORDER_PIN_TOPIC;
        if ($act == "unpin_topic") return GroupEventType::UNPIN_TOPIC;
        if ($act == "remove_topic") return GroupEventType::REMOVE_TOPIC;
        if ($act == "accept_remind") return GroupEventType::ACCEPT_REMIND;
        if ($act == "reject_remind") return GroupEventType::REJECT_REMIND;
        if ($act == "remind_topic") return GroupEventType::REMIND_TOPIC;

        return GroupEventType::UNKNOWN;
    }

    public static function getFriendEventType(string $act): int
    {
        if ($act == "add") return FriendEventType::ADD;
        if ($act == "remove") return FriendEventType::REMOVE;
        if ($act == "block") return FriendEventType::BLOCK;
        if ($act == "unblock") return FriendEventType::UNBLOCK;
        if ($act == "block_call") return FriendEventType::BLOCK_CALL;
        if ($act == "unblock_call") return FriendEventType::UNBLOCK_CALL;

        if ($act == "req_v2") return FriendEventType::REQUEST;
        if ($act == "reject") return FriendEventType::REJECT_REQUEST;
        if ($act == "undo_req") return FriendEventType::UNDO_REQUEST;

        if ($act == "seen_fr_req") return FriendEventType::SEEN_FRIEND_REQUEST;

        if ($act == "pin_unpin") return FriendEventType::PIN_UNPIN;
        if ($act == "pin_create") return FriendEventType::PIN_CREATE;

        return FriendEventType::UNKNOWN;
    }

    /**
     * @return array{data: mixed, error: array{message: string, code?: int}|null}
     */
    public static function handleZaloResponse(Context $ctx, ResponseInterface $response, bool $isEncrypted = true): array
    {
        $result = [
            "data" => null,
            "error" => null,
        ];

        if (!self::isOk($response)) {
            $result["error"] = [
                "message" => "Request failed with status code " . $response->getStatusCode(),
            ];

            return $result;
        }

        try {
            $jsonData = self::responseJson($response);

            if ($jsonData["error_code"] != 0) {
                $result["error"] = [
                    "message" => $jsonData["error_message"],
                    "code" => $jsonData["error_code"],
                ];

                return $result;
            }

            $decodedData = $isEncrypted ? self::jsonDecode((string) self::decodeAES($ctx->secretKey, $jsonData["data"])) : $jsonData;

            if ($decodedData["error_code"] != 0) {
                $result["error"] = [
                    "message" => $decodedData["error_message"],
                    "code" => $decodedData["error_code"],
                ];

                return $result;
            }

            $result["data"] = $decodedData["data"] ?? null;
        } catch (\Throwable $error) {
            Logger::of($ctx)->error("Failed to parse response data:", $error->getMessage());
            $result["error"] = [
                "message" => "Failed to parse response data",
            ];
        }

        return $result;
    }

    public static function resolveResponse(Context $ctx, ResponseInterface $res, ?callable $cb = null, bool $isEncrypted = true): mixed
    {
        $result = self::handleZaloResponse($ctx, $res, $isEncrypted);
        if ($result["error"]) throw new ZaloApiError((string) $result["error"]["message"], isset($result["error"]["code"]) ? (int) $result["error"]["code"] : null);
        if ($cb) return $cb($result);

        return $result["data"];
    }

    public static function generateZaloUUID(string $userAgent): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));

        return $uuid . "-" . md5($userAgent);
    }

    /**
     * Encrypts a 4-digit PIN to a 32-character hex string
     */
    public static function encryptPin(string $pin): string
    {
        return md5($pin);
    }

    /**
     * Verify if a PIN matches the hash
     *
     * @example
     * $encryptedPin = $api->getHiddenConversations()["pin"];
     * $isValid = Utils::validatePin($encryptedPin, "1234");
     */
    public static function validatePin(string $encryptedPin, string $pin): bool
    {
        return md5($pin) === $encryptedPin;
    }

    /**
     * Converts a hex color code to a negative color number used by Zalo API
     *
     * @example Utils::hexToNegativeColor('#00FF00'); // -16711936
     */
    public static function hexToNegativeColor(string $hex): int
    {
        if (!str_starts_with($hex, "#")) {
            $hex = "#" . $hex;
        }

        $hexValue = substr($hex, 1);
        if (strlen($hexValue) === 6) {
            $hexValue = "FF" . $hexValue;
        }
        $decimal = intval($hexValue, 16);

        return $decimal > 0x7fffffff ? $decimal - 4294967296 : $decimal;
    }

    /**
     * Converts a negative color number from Zalo API to hex color code
     *
     * @example Utils::negativeColorToHex(-16711936); // '#00ff00'
     */
    public static function negativeColorToHex(int $negativeColor): string
    {
        $positiveColor = $negativeColor + 4294967296;

        return "#" . str_pad(substr(dechex($positiveColor), -6), 6, "0", STR_PAD_LEFT);
    }

    public static function normalizeHolderName(?string $input = null): ?string
    {
        if (!$input) return null;

        $normalized = class_exists(\Normalizer::class) ? \Normalizer::normalize($input, \Normalizer::FORM_D) : $input;
        $normalized = preg_replace('/[\x{0300}-\x{036f}]/u', "", $normalized);
        $normalized = preg_replace('/[đĐ]/u', "d", $normalized);
        $normalized = mb_strtoupper($normalized, "UTF-8");
        $normalized = trim(preg_replace('/[^A-Z0-9]+/', " ", $normalized));

        return strlen($normalized) >= 5 ? $normalized : null;
    }
}
