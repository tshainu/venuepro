<?php
class Lang {
    private static $strings = [];
    private static $lang = 'en';

    public static function load($lang = 'en') {
        self::$lang = $lang;
        $file = ROOT_PATH . '/lang/' . $lang . '.php';
        if (file_exists($file)) {
            self::$strings = include $file;
        } else {
            self::$strings = include ROOT_PATH . '/lang/en.php';
        }
    }

    public static function t($key, $replace = []) {
        $str = self::$strings[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $str = str_replace(':' . $k, $v, $str);
        }
        return $str;
    }

    public static function current() {
        return self::$lang;
    }
}
