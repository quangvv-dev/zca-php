# Publish zca-php lên Packagist

Hai phiên bản dùng **một package Packagist**, **một repo GitHub**, và nằm trên **hai branch**:

| Branch | Tag | PHP | Thư mục làm việc hiện tại |
|---|---|---|---|
| `main` | `v2.x.x` | `^8.1` | `zca-php/` |
| `1.x` | `v1.x.x` | `^7.1.3 \|\| ^8.0` | `zca-php-1.x/` |

Composer luôn chọn bản cao nhất mà PHP của project đáp ứng được:

- PHP 8.1 trở lên sẽ nhận bản 2.x.
- PHP 7.1.3 đến 8.0 sẽ nhận bản 1.x.

Vì vậy người dùng chỉ cần chạy `composer require eric-sa/zca-php`.

## 1. Tên package

Tên package là `eric-sa/zca-php`, đã khai báo trong `"name"` ở **cả hai** file `composer.json`. Packagist lấy tên từ branch `main` và **không đổi được** sau khi submit. Namespace PHP vẫn là `Zca\`.

## 2. Tạo repo GitHub (public) rồi push hai branch

```bash
# bản 2.x -> branch main
cd zca-php
git init -b main
git add .
git commit -m "zca-php 2.0.0 (PHP 8.1+)"
git remote add origin https://github.com/quangvv-dev/zca-php.git
git push -u origin main
git tag v2.0.0 && git push origin v2.0.0

# bản 1.x -> branch 1.x
cd ../zca-php-1.x
git init -b 1.x
git add .
git commit -m "zca-php 1.0.0 (PHP 7.1.3+)"
git remote add origin https://github.com/quangvv-dev/zca-php.git
git push -u origin 1.x
git tag v1.0.0 && git push origin v1.0.0
```

`vendor/` và `composer.lock` đã nằm trong `.gitignore`. Library không commit lock file.


## 3. Đăng ký trên Packagist

1. Đăng nhập https://packagist.org bằng GitHub.
2. Vào **Submit** và dán URL repo `https://github.com/quangvv-dev/zca-php`.
3. Packagist tự đọc cả hai branch và các tag: `v2.0.0` thành bản 2.0.0, `v1.0.0` thành bản 1.0.0, branch `1.x` thành `1.x-dev`, branch `main` thành `2.x-dev`.
4. Bật auto-update: trong trang package, làm theo hướng dẫn GitHub hook (tài khoản đăng nhập bằng GitHub thường đã tự bật).

## 4. Phát hành bản mới

- Sửa lỗi hoặc cập nhật theo zca-js: sửa ở `main`, sau đó port sang `1.x`.
  - Code 1.x có thể sinh lại bằng Rector với set `DowngradeLevelSetList::DOWN_TO_PHP_71`.
  - Giữ nguyên các file viết riêng cho 1.x: `src/Async.php`, phần `run()`/`onWsMessage()` trong `src/Apis/Listener.php`, và `waitForUpload()` trong `src/Apis/UploadAttachment.php`.
- Tag theo SemVer trên từng branch, ví dụ `v2.0.1` trên `main` và `v1.0.1` trên `1.x`. Packagist cập nhật trong vài phút.
- Trước khi tag, chạy test:
  - 2.x: `php tests/offline.php`
  - 1.x trên PHP 7.1: `docker run --rm -v "$PWD":/app -w /app php:7.1-cli php tests/offline.php && php tests/nested-await.php`
