<?php

namespace Zca\Models;

class UserDeliveredMessage
{
    public int $type = ThreadType::User;

    /** @var array<string, mixed> */
    public array $data;
    public string $threadId;
    public bool $isSelf;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->threadId = (string) $data["deliveredUids"][0];
        $this->isSelf = false;
    }
}
