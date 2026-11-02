<?php
/**
 * Offline smoke test: fakes the Zalo servers with a Guzzle MockHandler and runs
 * login -> getUserInfo -> sendMessage (text, quote, mention) -> listener frame decoding.
 *
 * php tests/offline.php
 */

require __DIR__ . "/../vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Zca\Models\ThreadType;
use Zca\ParamsEncryptor;
use Zca\Utils;
use Zca\Zalo;

$secretKey = base64_encode(random_bytes(16));
$requests = [];

$serviceMap = [];
foreach (["other_contact","workspace","catalog","boards","downloadStickerUrl","sp_contact","zcloud_up_file","push_act","aext","zfamily","group_poll","group_cloud_message","media_store","file","auto_reply","sync_action","friendLan","friend","alias","zimsg","group_board","conversation","group","fallback_LP","friend_board","up_file","zavi","reaction","voice_call","profile","sticker","label","consent","zcloud","chat","todoUrl","recent_search","quick_message"] as $svc) {
    $serviceMap[$svc] = ["https://tt-{$svc}-wpa.chat.zalo.me"];
}

function aes(string $key, string $data): string
{
    return base64_encode(openssl_encrypt($data, "aes-" . (strlen($key) * 8) . "-cbc", $key, OPENSSL_RAW_DATA, str_repeat("\0", 16)));
}

$handler = function (RequestInterface $request) use (&$requests, $secretKey, $serviceMap) {
    $requests[] = $request;
    parse_str($request->getUri()->getQuery(), $query);
    $path = $request->getUri()->getPath();

    if ($path === "/api/login/getLoginInfo") {
        $n = ParamsEncryptor::processStr(strtoupper(md5($query["zcid_ext"])));
        $z = ParamsEncryptor::processStr($query["zcid"]);
        $key = implode("", array_slice($n["even"], 0, 8)) . implode("", array_slice($z["even"], 0, 12)) . implode("", array_slice(array_reverse($z["odd"]), 0, 12));
        $plain = Utils::jsonDecode(openssl_decrypt(base64_decode($query["params"]), "aes-256-cbc", $key, OPENSSL_RAW_DATA, str_repeat("\0", 16)));
        assert($plain["computer_name"] === "Web");
        $inner = json_encode(["error_code" => 0, "data" => [
            "uid" => "1000", "zpw_enk" => $secretKey, "zpw_service_map_v3" => $serviceMap, "zpw_ws" => ["wss://ws1-msg.chat.zalo.me/ws"],
        ]]);
        return new Response(200, ["Set-Cookie" => "zpw_sek=abc; Domain=.chat.zalo.me; Path=/; Secure"], json_encode(["error_code" => 0, "data" => aes($key, $inner)]));
    }
    if ($path === "/api/login/getServerInfo") {
        return new Response(200, [], json_encode(["data" => [
            "setttings" => ["features" => [
                "sharefile" => ["max_file" => 50, "max_size_share_file_v3" => 1024, "chunk_size_file" => 3145728, "restricted_ext_file" => ["exe"]],
                "socket" => ["retries" => ["1006" => ["max" => 3, "times" => [1000, 2000]]], "close_and_retry_codes" => [1006], "rotate_error_codes" => [1006], "ping_interval" => 20000],
            ]],
            "extra_ver" => ["phonebook" => 0],
        ]]));
    }

    parse_str((string) $request->getBody(), $body);
    $enc = $body["params"] ?? $query["params"] ?? null;
    $params = $enc === null ? null : Utils::jsonDecode(Utils::decodeAES($secretKey, $enc));
    switch (true) {
        case substr_compare($path, "/getprofiles/v2", -strlen("/getprofiles/v2")) === 0:
            $data = ["changed_profiles" => [], "unchanged_profiles" => [], "phonebook_version" => 0, "_params" => $params];
            break;
        default:
            $data = ["msgId" => 123, "_path" => $path, "_params" => $params];
            break;
    }
    return new Response(200, [], json_encode(["error_code" => 0, "data" => aes(base64_decode($secretKey), json_encode(["error_code" => 0, "data" => $data]))]));
};

$client = new Client(["handler" => HandlerStack::create(new MockHandler(array_fill(0, 50, $handler)))]);
$zalo = new Zalo(["httpClient" => $client, "checkUpdate" => false, "logging" => false]);

$check = function (string $name, bool $ok) {
    echo ($ok ? "OK   " : "FAIL ") . $name . PHP_EOL;
    if (!$ok) $GLOBALS["failed"] = true;
};

$api = $zalo->login([
    "imei" => "test-imei",
    "userAgent" => "Mozilla/5.0 test",
    "cookie" => [["name" => "zpsid", "value" => "xyz", "domain" => ".zalo.me", "path" => "/"]],
]);
$check("login uid", $api->getOwnId() === "1000");
$check("login cookie header sent", strpos($requests[0]->getHeaderLine("Cookie"), "zpsid=xyz") !== false);
$check("set-cookie stored", strpos($api->getCookie()->getCookieString("https://chat.zalo.me"), "zpw_sek=abc") !== false);
$check("zpw_ver in url", strpos((string) $requests[0]->getUri(), "zpw_ver=685&zpw_type=30") !== false);

$info = $api->getUserInfo("42");
$check("getUserInfo params", $info["_params"]["friend_pversion_map"] === ["42_0"] && $info["_params"]["imei"] === "test-imei");

$res = $api->sendMessage("xin chào", "42");
$check("sendMessage user sms", $res["message"]["_path"] === "/api/message/sms" && $res["message"]["_params"]["toid"] === "42" && !isset($res["message"]["_params"]["grid"]));
$check("sendMessage keys order", array_keys($res["message"]["_params"]) === ["message", "clientId", "imei", "ttl", "toid"]);

$res = $api->sendMessage(["msg" => "@all hi", "mentions" => [["pos" => 0, "uid" => "-1", "len" => 4]], "styles" => [["start" => 0, "len" => 2, "st" => \Zca\Models\TextStyle::Indent, "indentSize" => 2]], "urgency" => \Zca\Models\Urgency::Urgent], "99", ThreadType::Group);
$p = $res["message"]["_params"];
$check("sendMessage group mention", $res["message"]["_path"] === "/api/group/mention" && $p["grid"] === "99" && $p["visibility"] === 0);
$check("mention type all", Utils::jsonDecode($p["mentionInfo"])[0]["type"] === 1);
$check("indent style", Utils::jsonDecode($p["textProperties"])["styles"][0] === ["start" => 0, "len" => 2, "st" => "ind_20"]);
$check("urgency", $p["metaData"] === ["urgency" => 2]);

$quote = ["content" => "old", "msgType" => "webchat", "propertyExt" => null, "uidFrom" => "7", "msgId" => "11", "cliMsgId" => "12", "ts" => "13", "ttl" => 0];
$res = $api->sendMessage(["msg" => "reply", "quote" => $quote], "99", ThreadType::Group);
$check("quote path", $res["message"]["_path"] === "/api/group/quote" && $res["message"]["_params"]["qmsg"] === "old" && !isset($res["message"]["_params"]["qmsgAttach"]));

// every API can be built (runs each init())
$failedInit = [];
preg_match_all('/@method \S+ (\w+)\(/', file_get_contents(__DIR__ . "/../src/ApiMethods.php"), $m);
foreach ($m[1] as $name) {
    try {
        $api->resolve($name);
    } catch (\Throwable $e) {
        $failedInit[] = "$name: " . $e->getMessage();
    }
}
$check("all " . count($m[1]) . " APIs instantiate" . ($failedInit ? " (" . implode("; ", $failedInit) . ")" : ""), !$failedInit);

// call a set of common APIs with PHP warnings turned into exceptions
set_error_handler(function ($no, $str, $file, $line) { throw new \ErrorException($str, 0, $no, $file, $line); });
$msgData = ["msgId" => "100", "cliMsgId" => "101", "uidFrom" => "42", "ts" => "1700000000000", "msgType" => "webchat", "content" => "x", "ttl" => 0, "idTo" => "1000"];
$calls = [
    "addReaction" => function () use ($api) {
        return $api->addReaction(\Zca\Models\Reactions::HEART, ["data" => ["msgId" => "100", "cliMsgId" => "101"], "threadId" => "42", "type" => ThreadType::User]);
    },
    "addUserToGroup" => function () use ($api) {
        return $api->addUserToGroup(["1", "2"], "99");
    },
    "blockUser" => function () use ($api) {
        return $api->blockUser("42");
    },
    "changeGroupName" => function () use ($api) {
        return $api->changeGroupName("Nhóm mới", "99");
    },
    "createPoll" => function () use ($api) {
        return $api->createPoll(["question" => "Q?", "options" => ["a", "b"]], "99");
    },
    "createReminder" => function () use ($api) {
        return $api->createReminder(["title" => "Họp"], "99", ThreadType::Group);
    },
    "deleteMessage" => function () use ($api) {
        return $api->deleteMessage(["data" => ["cliMsgId" => "101", "msgId" => "100", "uidFrom" => "42"], "threadId" => "42", "type" => ThreadType::User], true);
    },
    "findUser" => function () use ($api) {
        return $api->findUser("0912345678");
    },
    "forwardMessage" => function () use ($api) {
        return $api->forwardMessage(["message" => "fw"], ["42", "43"]);
    },
    "getAllGroups" => function () use ($api) {
        return $api->getAllGroups();
    },
    "getGroupInfo" => function () use ($api) {
        return $api->getGroupInfo("99");
    },
    "lastOnline" => function () use ($api) {
        return $api->lastOnline("42");
    },
    "sendSeenEvent" => function () use ($api, $msgData) {
        return $api->sendSeenEvent([$msgData]);
    },
    "sendSticker" => function () use ($api) {
        return $api->sendSticker(["id" => 1, "cateId" => 2, "type" => 7], "42");
    },
    "sendTypingEvent" => function () use ($api) {
        return $api->sendTypingEvent("99", ThreadType::Group);
    },
    "setMute" => function () use ($api) {
        return $api->setMute([], "42");
    },
    "undo" => function () use ($api) {
        return $api->undo(["msgId" => "100", "cliMsgId" => "101"], "42");
    },
    "updateProfile" => function () use ($api) {
        return $api->updateProfile(["profile" => ["name" => "A", "dob" => "2000-01-01", "gender" => 0]]);
    },
];
foreach ($calls as $name => $call) {
    try {
        $call();
        $check("call $name", true);
    } catch (\Throwable $e) {
        $check("call $name: " . get_class($e) . " " . $e->getMessage() . " @" . basename($e->getFile()) . ":" . $e->getLine(), false);
    }
}
restore_error_handler();

// custom API
$api->custom("myApi", function (array $p) {
    return $p["props"]["x"] * 2 . $p["ctx"]->uid;
});
$check("custom api", $api->myApi(["x" => 21]) === "421000");

// listener frame decoding
$listener = $api->listener;
$messages = [];
$listener->on("message", function ($m) use (&$messages) { $messages[] = $m; });
$listener->on("error", function ($e) { echo "listener error: " . ($e instanceof \Throwable ? $e->getMessage() : json_encode($e)) . PHP_EOL; });
$cipherKey = base64_encode(random_bytes(32));
$frame = function (int $cmd, int $subCmd, array $payload) {
    return pack("C", 1) . pack("v", $cmd) . pack("C", $subCmd) . json_encode($payload);
};
$ws = (new ReflectionClass(\Ratchet\Client\WebSocket::class))->newInstanceWithoutConstructor();
$handle = (new ReflectionClass($listener))->getMethod("handleMessage");
if (PHP_VERSION_ID < 80100) $handle->setAccessible(true);
$mkMsg = function (string $payload) {
    return new \Ratchet\RFC6455\Messaging\Message() instanceof \Ratchet\RFC6455\Messaging\Message ? (function () use ($payload) {
        $m = new \Ratchet\RFC6455\Messaging\Message();
        $m->addFrame(new \Ratchet\RFC6455\Messaging\Frame($payload, true, \Ratchet\RFC6455\Messaging\Frame::OP_BINARY));
        return $m;
    })() : null;
};

$handle->invoke($listener, $ws, $mkMsg($frame(1, 1, ["key" => $cipherKey])));
$plain = gzcompress(json_encode(["data" => ["msgs" => [["msgId" => "9223372036854775899", "uidFrom" => "42", "idTo" => "0", "content" => "hello", "msgType" => "webchat"]]]]));
$iv = random_bytes(16); $aad = random_bytes(16); $tag = "";
$ct = openssl_encrypt($plain, "aes-256-gcm", base64_decode($cipherKey), OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
$handle->invoke($listener, $ws, $mkMsg($frame(501, 0, ["data" => rawurlencode(base64_encode($iv . $aad . $ct . $tag)), "encrypt" => 2])));
$check("listener message", count($messages) === 1 && $messages[0]->threadId === "42" && $messages[0]->data["idTo"] === "1000" && $messages[0]->data["msgId"] === "9223372036854775899");
\React\EventLoop\Loop::stop();

echo empty($GLOBALS["failed"]) ? "ALL PASSED\n" : "SOME FAILED\n";
