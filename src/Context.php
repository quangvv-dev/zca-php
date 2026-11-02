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

    /**
     * @var string|null
     */
    public $uid;
    /**
     * @var string|null
     */
    public $imei;
    /**
     * @var \Zca\Cookie\CookieJar|null
     */
    public $cookie;
    /**
     * @var string|null
     */
    public $userAgent;
    /**
     * @var string|null
     */
    public $language;
    /**
     * @var string|null
     */
    public $secretKey;
    /** @var array<string, string[]>|null */
    public $zpwServiceMap;
    /** @var array<string, mixed>|null */
    public $settings;
    /** @var array<string, mixed>|null */
    public $loginInfo;
    /** @var array<string, mixed>|null */
    public $extraVer;

    /**
     * @var \Zca\CallbacksMap
     */
    public $uploadCallbacks;

    /** @var array<string, mixed> */
    public $options;

    /**
     * @readonly
     * @var int
     */
    public $API_TYPE;
    /**
     * @readonly
     * @var int
     */
    public $API_VERSION;

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
