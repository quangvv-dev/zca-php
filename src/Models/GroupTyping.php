<?php

namespace Zca\Models;

class GroupTyping
{
    public int $type = ThreadType::Group;

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
        $this->threadId = (string) $data["gid"];
        $this->isSelf = false;
    }
}
