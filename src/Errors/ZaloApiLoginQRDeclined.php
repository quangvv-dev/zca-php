<?php

namespace Zca\Errors;

class ZaloApiLoginQRDeclined extends ZaloApiError
{
    public function __construct(string $message = "Login QR request declined")
    {
        parent::__construct($message);
    }

    public function getName(): string
    {
        return "ZaloApiLoginQRDeclined";
    }
}
