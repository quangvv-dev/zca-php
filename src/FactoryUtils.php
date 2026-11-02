<?php

namespace Zca;

use Psr\Http\Message\ResponseInterface;

/**
 * Port of the `utils` object passed to every API factory in zca-js.
 */
class FactoryUtils
{
    /**
     * @var \Zca\Context
     */
    private $ctx;
    /**
     * @var \Zca\Logger
     */
    public $logger;

    public function __construct(Context $ctx)
    {
        $this->ctx = $ctx;
        $this->logger = Logger::of($ctx);
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function makeURL(string $baseURL, array $params = [], bool $apiVersion = true): string
    {
        return Utils::makeURL($this->ctx, $baseURL, $params, $apiVersion);
    }

    public function encodeAES(string $data, int $t = 0): ?string
    {
        return Utils::encodeAES($this->ctx->secretKey, $data, $t);
    }

    /**
     * @param array<string, mixed>|null $options
     */
    public function request(string $url, ?array $options = null, bool $raw = false): ResponseInterface
    {
        return Utils::request($this->ctx, $url, $options, $raw);
    }

    /**
     * @param (callable(array{data: mixed, error: mixed}): mixed)|null $cb
     * @return mixed
     */
    public function resolve(ResponseInterface $res, ?callable $cb = null, bool $isEncrypted = true)
    {
        return Utils::resolveResponse($this->ctx, $res, $cb, $isEncrypted);
    }
}
