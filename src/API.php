<?php

namespace Zca;

use Zca\Apis\BaseApi;
use Zca\Apis\Listener;
use Zca\Errors\ZaloApiError;

/**
 * Port of the `API` class of zca-js.
 *
 * Every API of zca-js is available as a method with the same name and arguments, e.g.
 * `$api->sendMessage("hello", $threadId, ThreadType::User)`.
 * Each method is implemented by the class `Zca\Apis\<Name>` (see src/Apis).
 *
 * @mixin ApiMethods
 */
class API
{
    /**
     * @var \Zca\Context
     */
    private $ctx;
    /** @var array<string, string[]> */
    public $zpwServiceMap;
    /**
     * @var \Zca\Apis\Listener
     */
    public $listener;

    /** @var array<string, BaseApi> */
    private $instances = [];
    /** @var array<string, callable> */
    private $customApis = [];

    /**
     * @param array<string, string[]> $zpwServiceMap
     * @param string[] $wsUrls
     */
    public function __construct(Context $ctx, array $zpwServiceMap, array $wsUrls)
    {
        $this->ctx = $ctx;
        if (!$ctx->isSession()) throw new ZaloApiError("Invalid context");

        $this->zpwServiceMap = $zpwServiceMap;
        $this->listener = new Listener($ctx, $wsUrls);
    }

    /**
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (isset($this->customApis[$name])) {
            return ($this->customApis[$name])(...$arguments);
        }

        return ($this->resolve($name))(...$arguments);
    }

    /**
     * Get the instance implementing an API (built once, like the zca-js factories).
     */
    public function resolve(string $name): BaseApi
    {
        if (!isset($this->instances[$name])) {
            $class = self::apiClass($name);
            if (!$class) throw new \BadMethodCallException("Call to undefined API " . static::class . "::{$name}()");

            $this->instances[$name] = new $class($this->ctx, $this);
        }

        return $this->instances[$name];
    }

    public function hasApi(string $name): bool
    {
        return isset($this->customApis[$name]) || self::apiClass($name) !== null;
    }

    /**
     * @internal used by the `custom` API
     */
    public function defineCustom(string $name, callable $callback): void
    {
        $this->customApis[$name] = $callback;
    }

    /**
     * @return class-string<BaseApi>|null
     */
    private static function apiClass(string $name): ?string
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name)) return null;

        $class = "Zca\\Apis\\" . ucfirst($name);
        if (!class_exists($class) || !is_subclass_of($class, BaseApi::class)) return null;

        return $class;
    }
}
