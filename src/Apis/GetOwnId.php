<?php

namespace Zca\Apis;

/**
 * Port of src/apis/getOwnId.ts
 */
class GetOwnId extends BaseApi
{
    public function __invoke(): string
    {
        return $this->ctx->uid;
    }
}
