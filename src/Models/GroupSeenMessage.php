<?php

namespace Zca\Models;

class GroupSeenMessage
{
    public int $type = ThreadType::Group;

    /** @var array<string, mixed> */
    public array $data;
    public string $threadId;
    public bool $isSelf;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $uid, array $data)
    {
        $this->data = $data;
        $this->threadId = (string) $data["groupId"];
        $this->isSelf = in_array($uid, $data["seenUids"], false);
    }
}
