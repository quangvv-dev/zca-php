<?php

namespace Zca;

use Zca\Cookie\CookieJar;

/**
 * Port of `ContextBase` / `ContextSession` from zca-js.
 *
 * Options:
 *  - selfListen (bool, default false)
 *  - checkUpdate (bool, default true)
 *  - logging (bool, default true)
 *  - apiType (int, default 30)
 *  - apiVersion (int, default 685)
 *  - proxy (string|array|null) Guzzle proxy option, replaces `agent`
 *  - httpClient (\GuzzleHttp\ClientInterface|null) replaces `polyfill`
 *  - imageMetadataGetter (callable(string $filePath): ?array{width:int,height:int,size:int})
 *  - logger (\Psr\Log\LoggerInterface|null) optional, logs are sent here instead of stdout
 *  - uploadTimeout (int seconds, default 300) how long to wait for the websocket `file_done` event
 */
class Context
{
    public const MAX_MESSAGES_PER_SEND = 50;

    public ?string $uid = null;
    public ?string $imei = null;
    public ?CookieJar $cookie = null;
    public ?string $userAgent = null;
    public ?string $language = null;
    public ?string $secretKey = null;
    /** @var array<string, string[]>|null */
    public ?array $zpwServiceMap = null;
    /** @var array<string, mixed>|null */
    public ?array $settings = null;
    /** @var array<string, mixed>|null */
    public ?array $loginInfo = null;
    /** @var array<string, mixed>|null */
    public ?array $extraVer = null;

    public CallbacksMap $uploadCallbacks;

    /** @var array<string, mixed> */
    public array $options;

    public readonly int $API_TYPE;
    public readonly int $API_VERSION;

    public function __construct(int $apiType = 30, int $apiVersion = 685)
    {
        $this->API_TYPE = $apiType;
        $this->API_VERSION = $apiVersion;
        $this->uploadCallbacks = new CallbacksMap();
        $this->options = [
            "selfListen" => false,
            "checkUpdate" => true,
            "logging" => true,
            "httpClient" => null,
        ];
        $this->secretKey = null;
    }

    public static function create(?int $apiType = null, ?int $apiVersion = null): self
    {
        return new self($apiType ?? 30, $apiVersion ?? 685);
    }

    /**
     * Equivalent of `isContextSession(ctx)`
     */
    public function isSession(): bool
    {
        return !empty($this->secretKey);
    }
}
