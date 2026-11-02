<?php

namespace Zca\Cookie;

use GuzzleHttp\Cookie\SetCookie;

/**
 * Minimal port of the parts of `tough-cookie`'s CookieJar used by zca-js.
 *
 * Cookies are stored as arrays using tough-cookie's serialized format
 * (key, value, domain, path, expires, httpOnly, secure, hostOnly, ...)
 * so that cookies exported from zca-js can be imported here and vice versa.
 */
class CookieJar
{
    /** @var array<int, array<string, mixed>> */
    private array $cookies = [];

    /**
     * Equivalent of `toughCookie.Cookie.fromJSON(...)` + `jar.setCookieSync(cookie, url)`
     *
     * @param array<string, mixed> $cookie serialized cookie (tough-cookie or browser-extension format)
     */
    public function setCookieFromJSON(array $cookie, string $url): void
    {
        $key = $cookie["key"] ?? ($cookie["name"] ?? null);
        if ($key === null || !array_key_exists("value", $cookie)) {
            throw new \InvalidArgumentException("Invalid cookie");
        }

        $expires = $cookie["expires"] ?? null;
        // like tough-cookie, `expirationDate` (browser-extension format) is ignored
        if (is_string($expires)) {
            $expires = $expires === "Infinity" ? null : self::parseDate($expires);
        }

        $this->store([
            "key" => (string) $key,
            "value" => (string) $cookie["value"],
            "domain" => isset($cookie["domain"]) ? strtolower(ltrim((string) $cookie["domain"], ".")) : null,
            "path" => $cookie["path"] ?? null,
            "expires" => $expires,
            "maxAge" => $cookie["maxAge"] ?? null,
            "secure" => (bool) ($cookie["secure"] ?? false),
            "httpOnly" => (bool) ($cookie["httpOnly"] ?? false),
            "hostOnly" => $cookie["hostOnly"] ?? null,
            "sameSite" => $cookie["sameSite"] ?? null,
            "creation" => $cookie["creation"] ?? null,
            "lastAccessed" => $cookie["lastAccessed"] ?? null,
        ], $url);
    }

    /**
     * Equivalent of `toughCookie.Cookie.parse(setCookieHeader)` + `jar.setCookie(cookie, url)`
     */
    public function setCookie(string $setCookieHeader, string $url): void
    {
        $parsed = SetCookie::fromString($setCookieHeader);
        if ($parsed->getName() === "" || $parsed->getName() === null) return;

        $expires = null;
        if ($parsed->getMaxAge() !== null) {
            $expires = (int) ((time() + (int) $parsed->getMaxAge()) * 1000);
        } elseif ($parsed->getExpires() !== null) {
            $expires = (int) $parsed->getExpires() * 1000;
        }

        $this->store([
            "key" => $parsed->getName(),
            "value" => (string) $parsed->getValue(),
            "domain" => $parsed->getDomain() ? strtolower(ltrim($parsed->getDomain(), ".")) : null,
            "path" => $parsed->getPath() !== "/" || stripos($setCookieHeader, "path=") !== false ? $parsed->getPath() : null,
            "expires" => $expires,
            "maxAge" => $parsed->getMaxAge(),
            "secure" => $parsed->getSecure(),
            "httpOnly" => $parsed->getHttpOnly(),
            "hostOnly" => null,
            "sameSite" => self::extractSameSite($setCookieHeader),
            "creation" => null,
            "lastAccessed" => null,
        ], $url);
    }

    /**
     * Equivalent of `jar.getCookieString(url)`
     */
    public function getCookieString(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts["host"] ?? "");
        $path = $parts["path"] ?? "/";
        if ($path === "") $path = "/";
        $secure = in_array(strtolower($parts["scheme"] ?? ""), ["https", "wss"], true);
        $now = self::nowMs();

        $matched = [];
        foreach ($this->cookies as $i => $cookie) {
            if ($cookie["expires"] !== null && $cookie["expires"] <= $now) {
                unset($this->cookies[$i]);
                continue;
            }

            if ($cookie["hostOnly"]) {
                if ($cookie["domain"] !== $host) continue;
            } elseif (!self::domainMatch($host, $cookie["domain"])) {
                continue;
            }

            if (!self::pathMatch($path, $cookie["path"] ?? "/")) continue;
            if ($cookie["secure"] && !$secure) continue;

            $this->cookies[$i]["lastAccessed"] = self::isoDate($now);
            $matched[] = $this->cookies[$i];
        }

        // sort by path length desc, then creation asc (same as tough-cookie's cookieCompare)
        usort($matched, function ($a, $b) {
            $cmp = strlen($b["path"] ?? "/") - strlen($a["path"] ?? "/");
            if ($cmp !== 0) return $cmp;
            return ($a["_creationIndex"] ?? 0) <=> ($b["_creationIndex"] ?? 0);
        });

        return implode("; ", array_map(fn($c) => $c["key"] === "" ? $c["value"] : $c["key"] . "=" . $c["value"], $matched));
    }

    /**
     * Equivalent of `jar.toJSON()`
     *
     * @return array{version: string, storeType: string, rejectPublicSuffixes: bool, cookies: array<int, array<string, mixed>>}
     */
    public function toJSON(): array
    {
        $cookies = [];
        foreach ($this->cookies as $cookie) {
            $out = [
                "key" => $cookie["key"],
                "value" => $cookie["value"],
            ];
            if ($cookie["expires"] !== null) $out["expires"] = self::isoDate($cookie["expires"]);
            if ($cookie["maxAge"] !== null) $out["maxAge"] = $cookie["maxAge"];
            if ($cookie["domain"] !== null) $out["domain"] = $cookie["domain"];
            if ($cookie["path"] !== null) $out["path"] = $cookie["path"];
            if ($cookie["secure"]) $out["secure"] = true;
            if ($cookie["httpOnly"]) $out["httpOnly"] = true;
            if ($cookie["sameSite"] !== null) $out["sameSite"] = $cookie["sameSite"];
            $out["hostOnly"] = (bool) $cookie["hostOnly"];
            $out["creation"] = $cookie["creation"];
            $out["lastAccessed"] = $cookie["lastAccessed"];
            $cookies[] = $out;
        }

        return [
            "version" => "tough-cookie@5.0.0",
            "storeType" => "MemoryCookieStore",
            "rejectPublicSuffixes" => true,
            "cookies" => $cookies,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllCookies(): array
    {
        return $this->toJSON()["cookies"];
    }

    private int $creationIndex = 0;

    /**
     * @param array<string, mixed> $cookie
     */
    private function store(array $cookie, string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts["host"] ?? "null");
        $now = self::nowMs();

        if ($cookie["domain"] !== null && $cookie["domain"] !== "") {
            if (!self::domainMatch($host, $cookie["domain"])) {
                throw new \RuntimeException("Cookie not in this host's domain. Cookie:{$cookie["domain"]} Request:{$host}");
            }
            if ($cookie["hostOnly"] === null) $cookie["hostOnly"] = false;
        } else {
            $cookie["hostOnly"] = true;
            $cookie["domain"] = $host;
        }

        if ($cookie["path"] === null || $cookie["path"] === "" || $cookie["path"][0] !== "/") {
            $cookie["path"] = self::defaultPath($parts["path"] ?? "/");
            $cookie["pathIsDefault"] = true;
        }

        // remove existing cookie with same key/domain/path
        foreach ($this->cookies as $i => $existing) {
            if ($existing["key"] === $cookie["key"] && $existing["domain"] === $cookie["domain"] && $existing["path"] === $cookie["path"]) {
                $cookie["creation"] = $existing["creation"];
                $cookie["_creationIndex"] = $existing["_creationIndex"];
                unset($this->cookies[$i]);
            }
        }

        if ($cookie["expires"] !== null && $cookie["expires"] <= $now) {
            // expired cookie == deletion
            $this->cookies = array_values($this->cookies);
            return;
        }

        $cookie["creation"] ??= self::isoDate($now);
        $cookie["lastAccessed"] = self::isoDate($now);
        $cookie["_creationIndex"] ??= $this->creationIndex++;

        $this->cookies[] = $cookie;
        $this->cookies = array_values($this->cookies);
    }

    private static function domainMatch(string $host, ?string $domain): bool
    {
        if ($domain === null) return false;
        if ($host === $domain) return true;
        if (filter_var($host, FILTER_VALIDATE_IP)) return false;

        return str_ends_with($host, "." . $domain);
    }

    private static function pathMatch(string $reqPath, string $cookiePath): bool
    {
        if ($cookiePath === $reqPath) return true;
        if (str_starts_with($reqPath, $cookiePath)) {
            if (str_ends_with($cookiePath, "/")) return true;
            if (substr($reqPath, strlen($cookiePath), 1) === "/") return true;
        }

        return false;
    }

    private static function defaultPath(string $path): string
    {
        if ($path === "" || $path[0] !== "/") return "/";
        $idx = strrpos($path, "/");
        if ($idx === 0) return "/";

        return substr($path, 0, $idx);
    }

    private static function extractSameSite(string $header): ?string
    {
        if (preg_match('/;\s*samesite\s*=\s*([^;]+)/i', $header, $m)) {
            return strtolower(trim($m[1]));
        }

        return null;
    }

    private static function parseDate(string $date): ?int
    {
        $ts = strtotime($date);

        return $ts === false ? null : $ts * 1000;
    }

    private static function isoDate(int $ms): string
    {
        return gmdate("Y-m-d\TH:i:s", intdiv($ms, 1000)) . sprintf(".%03dZ", $ms % 1000);
    }

    private static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
