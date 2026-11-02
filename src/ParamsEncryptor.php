<?php

namespace Zca;

use Zca\Errors\ZaloApiError;

class ParamsEncryptor
{
    /**
     * @var string|null
     */
    private $zcid;
    /**
     * @var string
     */
    private $enc_ver;
    /**
     * @var string
     */
    private $zcid_ext;
    /**
     * @var string|null
     */
    private $encryptKey;

    public function __construct(int $type, string $imei, int $firstLaunchTime)
    {
        $this->enc_ver = "v2";
        $this->zcid = null;
        $this->encryptKey = null;

        $this->createZcid($type, $imei, $firstLaunchTime);
        $this->zcid_ext = self::randomString();
        $this->createEncryptKey();
    }

    public function getEncryptKey(): string
    {
        if (!$this->encryptKey) throw new ZaloApiError("getEncryptKey: didn't create encryptKey yet");

        return $this->encryptKey;
    }

    public function createZcid(int $type, string $imei, int $firstLaunchTime): void
    {
        if (!$type || !$imei || !$firstLaunchTime) throw new ZaloApiError("createZcid: missing params");
        $msg = "{$type},{$imei},{$firstLaunchTime}";
        $s = self::encodeAES("3FC4F0D2AB50057BCE0D90D9187A22B1", $msg, "hex", true);
        $this->zcid = $s;
    }

    public function createEncryptKey(int $e = 0): bool
    {
        $t = function (string $e, string $t): bool {
            ["even" => $n] = self::processStr($e);
            ["even" => $a, "odd" => $s] = self::processStr($t);
            if (!$n || !$a || !$s) return false;
            $i = implode("", array_slice($n, 0, 8)) . implode("", array_slice($a, 0, 12)) . implode("", array_slice(array_reverse($s), 0, 12));
            $this->encryptKey = $i;

            return true;
        };
        if (!$this->zcid || !$this->zcid_ext) throw new ZaloApiError("createEncryptKey: zcid or zcid_ext is null");
        try {
            $n = strtoupper(md5($this->zcid_ext));
            if ($t($n, $this->zcid) || !($e < 3)) return false;
            $this->createEncryptKey($e + 1);
        } catch (\Throwable $exception) {
            if ($e < 3) $this->createEncryptKey($e + 1);
        }

        return true;
    }

    /**
     * @return array{zcid: string, zcid_ext: string, enc_ver: string}|null
     */
    public function getParams(): ?array
    {
        return $this->zcid
            ? [
                "zcid" => $this->zcid,
                "zcid_ext" => $this->zcid_ext,
                "enc_ver" => $this->enc_ver,
            ]
            : null;
    }

    /**
     * @return array{even: string[]|null, odd: string[]|null}
     * @param mixed $e
     */
    public static function processStr($e): array
    {
        if (!$e || !is_string($e))
            return [
                "even" => null,
                "odd" => null,
            ];
        $t = [];
        $n = [];
        foreach (mb_str_split($e) as $i => $char) {
            if ($i % 2 === 0) $t[] = $char;
            else $n[] = $char;
        }

        return [
            "even" => $t,
            "odd" => $n,
        ];
    }

    private static function randomHex(int $length): string
    {
        // equivalent of Math.random().toString(16).substr(2, length)
        return (string) substr(bin2hex(random_bytes(8)), 0, min($length, 13));
    }

    public static function randomString(?int $e = null, ?int $t = null): string
    {
        $n = $e ?: 6;
        $a = $t && $e && $t > $e ? $t : 12;
        $s = random_int($n, $a);
        if ($s > 12) {
            $e = "";
            for (; $s > 0;) {
                $e .= self::randomHex($s > 12 ? 12 : $s);
                $s -= 12;
            }

            return $e;
        }

        return self::randomHex($s);
    }

    /**
     * @param "hex"|"base64" $type
     */
    public static function encodeAES(string $e, string $message, string $type, bool $uppercase, int $s = 0): ?string
    {
        if (!$message) return null;
        try {
            $encrypted = openssl_encrypt($message, "aes-256-cbc", $e, OPENSSL_RAW_DATA, str_repeat("\0", 16));
            if ($encrypted === false) throw new \RuntimeException("encrypt failed");
            $encrypted = $type == "hex" ? bin2hex($encrypted) : base64_encode($encrypted);

            return $uppercase ? strtoupper($encrypted) : $encrypted;
        } catch (\Throwable $exception) {
            return $s < 3 ? self::encodeAES($e, $message, $type, $uppercase, $s + 1) : null;
        }
    }
}
