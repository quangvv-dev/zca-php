<?php

namespace Zca\Models;

class UserMessage
{
    public int $type = ThreadType::User;

    /** @var array<string, mixed> TMessage */
    public array $data;
    public string $threadId;
    /**
     * true if the message is sent by the logged in account
     */
    public bool $isSelf;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $uid, array $data)
    {
        $this->threadId = (string) ($data["uidFrom"] == "0" ? $data["idTo"] : $data["uidFrom"]);
        $this->isSelf = $data["uidFrom"] == "0";

        if ($data["idTo"] == "0") $data["idTo"] = $uid;
        if ($data["uidFrom"] == "0") $data["uidFrom"] = $uid;
        if (!empty($data["quote"])) {
            $data["quote"]["ownerId"] = (string) $data["quote"]["ownerId"];
        }
        $this->data = $data;
    }
}
