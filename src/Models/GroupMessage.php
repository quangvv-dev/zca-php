<?php

namespace Zca\Models;

class GroupMessage
{
    /**
     * @var int
     */
    public $type = ThreadType::Group;

    /** @var array<string, mixed> TGroupMessage */
    public $data;
    /**
     * @var string
     */
    public $threadId;
    /**
     * true if the message is sent by the logged in account
     * @var bool
     */
    public $isSelf;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $uid, array $data)
    {
        $this->threadId = (string) $data["idTo"];
        $this->isSelf = $data["uidFrom"] == "0";

        if ($data["uidFrom"] == "0") $data["uidFrom"] = $uid;
        if (!empty($data["quote"])) {
            $data["quote"]["ownerId"] = (string) $data["quote"]["ownerId"];
        }
        $this->data = $data;
    }
}
