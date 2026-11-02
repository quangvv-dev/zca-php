<?php

namespace Zca\Errors;

class ZaloApiError extends \Exception
{
    /**
     * @var int|null
     */
    public $zaloCode;

    public function __construct(string $message = "", ?int $code = null)
    {
        parent::__construct($message, $code ?? 0);

        $this->zaloCode = $code ?: null;
    }

    public function getName(): string
    {
        return "ZcaApiError";
    }
}
