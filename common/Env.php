<?php
/**
 * common/Env.php — .env ファイルの簡易ローダー
 *
 * 外部ライブラリに依存せず、KEY=VALUE 形式の行を読み込んで
 * getenv() で参照できるようにする。ドキュメントルートの1つ上
 * (Webルート外) に .env を置く想定。
 */

final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");
            if ($key === '') {
                continue;
            }
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
