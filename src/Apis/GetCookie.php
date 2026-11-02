<?php

namespace Zca\Apis;

use Zca\Cookie\CookieJar;

/**
 * Port of src/apis/getCookie.ts
 */
class GetCookie extends BaseApi
{
    /**
     * Get the current cookie jar (use `->toJSON()` to serialize it)
     */
    public function __invoke(): CookieJar
    {
        return $this->ctx->cookie;
    }
}
