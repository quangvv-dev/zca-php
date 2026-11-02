<?php

namespace Zca\Apis;

use Zca\Context;

/**
 * Port of src/apis/getContext.ts
 */
class GetContext extends BaseApi
{
    public function __invoke(): Context
    {
        return $this->ctx;
    }
}
