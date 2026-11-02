# zca-php

Bản port PHP của [zca-js](https://github.com/RFS-ADRENO/zca-js) (Unofficial Zalo API). Logic giữ nguyên như zca-js 2.2.0: cùng endpoint, cùng tham số, cùng cách mã hoá và cùng thông báo lỗi. API có cùng tên và cùng thứ tự tham số với bản JS.

> [!WARNING]
> Đây là API không chính thức, hoạt động bằng cách giả lập Zalo Web. Tài khoản có thể bị khoá. Bạn tự chịu rủi ro khi sử dụng.

## Phiên bản

| Phiên bản | Branch | PHP | Laravel |
|---|---|---|---|
| **2.x** (bản này) | `main` | 8.1+ | 9, 10, 11, 12 (và 8 nếu chạy trên PHP 8.1+) |
| **1.x** | `1.x` | 7.1.3 – 8.x | 5.7, 5.8, 6, 7, 8 |

Hai bản có cùng API. Khi chạy `composer require eric-sa/zca-php`, Composer tự chọn bản phù hợp với phiên bản PHP của project.

Yêu cầu chung: ext-openssl, ext-zlib, ext-json, ext-mbstring.

## Cài đặt

```bash
composer require eric-sa/zca-php
```

Khi chưa publish lên Packagist, có thể cài qua repository `path` hoặc `vcs` trong `composer.json` của project Laravel:

```json
{
    "repositories": [
        { "type": "path", "url": "../zca-js/zca-php" }
    ],
    "require": {
        "eric-sa/zca-php": "*"
    }
}
```

```bash
composer require eric-sa/zca-php:*
```

Nếu dùng git, thay bằng `{ "type": "vcs", "url": "https://github.com/quangvv-dev/zca-php.git" }`.

## Khác biệt so với zca-js

| zca-js | zca-php |
|---|---|
| `await api.sendMessage(...)` | `$api->sendMessage(...)` (chạy đồng bộ) |
| enum `ThreadType.User` | hằng số `Zca\Models\ThreadType::User` |
| object `{ msg: "hi" }` | mảng `["msg" => "hi"]` |
| kết quả là object | kết quả là mảng associative |
| `AttachmentSource` dạng `{ data: Buffer, filename, metadata }` | `["data" => $binaryString, "filename" => "a.png", "metadata" => ["totalSize" => .., "width" => .., "height" => ..]]` |
| `polyfill` / `agent` | `httpClient` (Guzzle `ClientInterface`) / `proxy` (Guzzle proxy) |
| `imageMetadataGetter` bắt buộc khi gửi ảnh theo đường dẫn | mặc định dùng `getimagesize()`, vẫn có thể truyền hàm riêng |
| listener dùng `ws` | listener dùng ReactPHP + Pawl; `start()` rồi `React\EventLoop\Loop::run()` |
| `loginQR` callback `actions.retry()/abort()` | giống hệt; vì PHP chạy đồng bộ, lựa chọn được áp dụng ngay khi callback return |
| `api.custom(name, cb)` | `$api->custom($name, fn(array $p) => ...)` với `$p["ctx"]`, `$p["utils"]`, `$p["props"]` |

Các option thêm:

- `logger`: một PSR-3 logger, dùng thay cho việc in log ra stdout.
- `uploadTimeout`: số giây chờ sự kiện `file_done` từ websocket khi upload video hoặc file. Mặc định là 300.

## Sử dụng

### Đăng nhập bằng QR

```php
use Zca\Zalo;
use Zca\Models\LoginQRCallbackEventType;

$zalo = new Zalo();
$api = $zalo->loginQR(["qrPath" => "qr.png"], function (array $event) {
    if ($event["type"] === LoginQRCallbackEventType::QRCodeGenerated) {
        $event["actions"]["saveToFile"]();          // hoặc lưu $event["data"]["image"] (base64)
    }
    if ($event["type"] === LoginQRCallbackEventType::GotLoginInfo) {
        // lưu lại để đăng nhập bằng cookie lần sau
        file_put_contents("credentials.json", json_encode($event["data"]));
    }
});
```

### Đăng nhập bằng cookie

```php
$credentials = json_decode(file_get_contents("credentials.json"), true);
// ["imei" => ..., "cookie" => [...], "userAgent" => ..., "language" => "vi"]

$api = (new Zalo())->login($credentials);
```

Bạn có thể dùng cookie và imei xuất ra từ zca-js. Định dạng cookie tương thích với tough-cookie.

### Gửi tin nhắn

```php
use Zca\Models\ThreadType;
use Zca\Models\TextStyle;

$api->sendMessage("Xin chào", $userId, ThreadType::User);

$api->sendMessage([
    "msg" => "Chào cả nhóm",
    "styles" => [["start" => 0, "len" => 4, "st" => TextStyle::Bold]],
    "attachments" => ["/path/to/image.jpg"],
], $groupId, ThreadType::Group);

$api->addReaction(\Zca\Models\Reactions::HEART, [
    "data" => ["msgId" => $msgId, "cliMsgId" => $cliMsgId],
    "threadId" => $threadId,
    "type" => ThreadType::User,
]);
```

Tất cả API của zca-js đều có trong `src/Apis/<TênApi>.php` và được gọi bằng `$api->tenApi(...)`.

### Lắng nghe tin nhắn (chạy dưới dạng process dài, ví dụ artisan command hoặc supervisor)

```php
$api->listener->on("message", function ($message) use ($api) {
    if (is_string($message->data["content"]) && !$message->isSelf) {
        $api->sendMessage("echo: " . $message->data["content"], $message->threadId, $message->type);
    }
});

$api->listener->start(["retryOnClose" => true]);
$api->listener->run();   // tương đương Loop::run()
```

Mỗi account chỉ có một listener web hoạt động tại một thời điểm.

> [!IMPORTANT]
> Upload **video hoặc file** (không phải ảnh) cần listener đang chạy trong cùng process, giống như zca-js. Zalo trả URL file qua websocket (`file_done`). Gửi text và ảnh thì không cần listener.

## Laravel

Service provider và facade được đăng ký tự động.

```bash
php artisan vendor:publish --tag=zca-config
```

```php
use Zca\Laravel\Facades\Zalo;

$api = Zalo::login($credentials);   // hoặc app(\Zca\Zalo::class)->login(...)
$api->sendMessage("Hello", $threadId);
```

Gợi ý:

- **Gửi tin trong request HTTP hoặc queue job:** gọi `login($credentials)` với credentials lưu trong DB hoặc cache. Mỗi lần login tốn 2 request HTTP.
- **Nhận tin:** viết một artisan command chạy listener và để supervisor quản lý.
- **Đăng nhập QR:** chạy trong artisan command hoặc queue job, vì thao tác này block tới 100 giây. Ảnh QR (base64) lấy từ event `QRCodeGenerated`.

## Test offline

```bash
php tests/offline.php
```

Test giả lập server Zalo bằng Guzzle MockHandler để kiểm tra các luồng: login, mã hoá và giải mã, sendMessage, custom API và giải mã frame websocket.
