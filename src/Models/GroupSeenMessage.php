<?php

namespace Zca\Models;

class GroupSeenMessage
{
    /**
     * @var int
     */
    public $type = ThreadType::Group;

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
    public function __construct(string $uid, array $data)
    {
        $this->data = $data;
        $this->threadId = (string) $data["groupId"];
        $this->isSelf = in_array($uid, $data["seenUids"], false);
    }
}
