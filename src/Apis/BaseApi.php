<?php

namespace Zca\Apis;

use Zca\API;
use Zca\Context;
use Zca\Errors\ZaloApiError;
use Zca\FactoryUtils;

/**
 * Port of `apiFactory()` from zca-js.
 *
 * Each API is a class extending BaseApi and implementing `__invoke(...)`.
 * Code that ran once inside the JS factory (building service URLs, ...) goes into `init()`.
 */
abstract class BaseApi
{
    /**
     * @var \Zca\Context
     */
    protected $ctx;
    /**
     * @var \Zca\API
     */
    protected $api;
    /**
     * @var \Zca\FactoryUtils
     */
    protected $utils;

    public function __construct(Context $ctx, API $api)
    {
        $this->ctx = $ctx;
        $this->api = $api;
        if (!$ctx->isSession()) throw new ZaloApiError("Invalid context");

        $this->utils = new FactoryUtils($ctx);
        $this->init();
    }

    protected function init(): void {}
}
