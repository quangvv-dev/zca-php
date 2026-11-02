<?php

namespace Zca\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Zca\API login(array $credentials)
 * @method static \Zca\API loginQR(?array $options = null, ?callable $callback = null)
 *
 * @see \Zca\Zalo
 */
class Zalo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return "zca";
    }
}
