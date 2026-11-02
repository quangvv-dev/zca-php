<?php

namespace Zca\Errors;

class ZaloApiLoginQRAborted extends ZaloApiError
{
    public function __construct(string $message = "Operation aborted")
    {
        parent::__construct($message);
    }

    public function getName(): string
    {
        return "ZaloApiLoginQRAborted";
    }
}
