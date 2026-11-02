<?php

namespace Zca\Models;

class UserTyping
{
    /**
     * @var int
     */
    public $type = ThreadType::User;

    /** @var array<string, mixed> */
    public $data;
    /**
     * @var string
     */
    public $threadId;
    /**
     * @var bool
     */
    public $isSelf;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->threadId = (string) $data["uid"];
        $this->isSelf = false;
    }
}
