<?php

namespace App\Utils;

class ClientUa
{
    public static function isIosAllowed($ua)
    {
        $items = self::items(config('v2board.client_ua_ios', ''));
        if (!$items) {
            return true;
        }

        foreach ($items as $item) {
            if (stripos($ua, $item) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function isClientAllowed($ua)
    {
        $items = self::items(config('v2board.client_ua', ''));
        if (!$items) {
            return true;
        }

        return in_array($ua, $items, true);
    }

    private static function items($value)
    {
        $items = is_array($value) ? $value : explode(',', (string)$value);

        return array_values(array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $items), function ($item) {
            return $item !== '';
        }));
    }

}
