<?php
namespace spotplayer\inc\Utils;

defined('ABSPATH') || exit;

final class Helpers {
    public static function array_get(array $arr, string $key, $default = null) {
        return $arr[$key] ?? $default;
    }
}
