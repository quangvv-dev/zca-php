<?php

namespace Zca\Models;

class UserSeenMessage
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
        $this->threadId = (string) $data["idTo"];
        $this->isSelf = false;
    }
}
