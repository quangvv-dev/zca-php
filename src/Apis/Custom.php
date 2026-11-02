<?php

namespace Zca\Apis;

use Zca\Errors\ZaloApiError;

/**
 * Register a custom API.
 *
 * @example
 * $api->custom("getProfile", function (array $p) {
 *     // $p["ctx"] Context, $p["utils"] FactoryUtils, $p["props"] mixed
 *     return $p["utils"]->resolve($p["utils"]->request(...));
 * });
 * $api->getProfile($props);
 */
class Custom extends BaseApi
{
    /**
     * @param callable(array{ctx: \Zca\Context, utils: \Zca\FactoryUtils, props: mixed}): mixed $callback
     */
    public function __invoke(string $name, callable $callback): void
    {
        if ($this->api->hasApi($name)) throw new ZaloApiError("Cannot redefine property: {$name}");

        $ctx = $this->ctx;
        $utils = $this->utils;
        $this->api->defineCustom($name, function (mixed $props = null) use ($callback, $ctx, $utils) {
            return $callback(["ctx" => $ctx, "utils" => $utils, "props" => $props]);
        });
    }
}
