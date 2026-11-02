<?php

namespace Zca\Models;

class Reaction
{
    /** @var array<string, mixed> TReaction */
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
     * @var bool
     */
    public $isGroup;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $uid, array $data, bool $isGroup)
    {
        $this->threadId = (string) ($isGroup || $data["uidFrom"] == "0" ? $data["idTo"] : $data["uidFrom"]);
        $this->isSelf = $data["uidFrom"] == "0";
        $this->isGroup = $isGroup;

        if ($data["idTo"] == "0") $data["idTo"] = $uid;
        if ($data["uidFrom"] == "0") $data["uidFrom"] = $uid;
        $this->data = $data;
    }
}
