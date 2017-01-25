<?php

namespace Minimalism;


// TODO
// 将 array 配置 自动转换成 修改成 ini配置
// 将ini配置迁移到 运维配置中心

/**
 * Class Yaconf
 * @package Minimalism
 *
 * Yaconf php版本
 * 用于swoole长生命周期的模型中
 *
 * http://www.laruence.com/2015/06/12/3051.html
 * https://github.com/laruence/yaconf/tree/master
 * http://php.net/manual/en/function.parse-ini-file.php
 */
final class Yaconf
{
    public static $conf;

    public static function get($name, $default = null)
    {
        $segs = explode('.', $name);
        if (empty($segs)) {
            return $default;
        }

        $target = self::$conf;
        foreach ($segs as $seg) {
            if ($target === null || !isset($target[$seg])) {
                return $default;
            }
            $target = $target[$seg];
        }
        return $target;
    }

    public static function has($name)
    {
        return self::get($name) !== null;
    }

    public static function array2ini(array $arr)
    {

    }

    public static function parseFile($file)
    {
        return self::parseStr(file_get_contents($file));
    }

    /**
     * @param string $ini
     * @return array|bool
     */
    public static function parseStr($ini)
    {
        $result = parse_ini_string($ini, true, 0);
        if ($result === false) {
            return false;
        }

        foreach ($result as $k => $v) {
            $isMap = strpos($k, '.');
            $hasParent = strpos($k, ':');

            if ($hasParent && substr_count($k, ':') !== 1) {
                throw new \RuntimeException("bad inherit section: $k");
            }

            if ($isMap && $hasParent) {
                throw new \RuntimeException("bad key: $k");
            }

            if ($hasParent) {
                list($self, $parent) = explode(':', $k);
                if (!isset($result[$parent])) {
                    throw new \RuntimeException("bad inherit key: $k");
                }
                $result[$self] = array_merge($result[$parent], $result[$k]);
                unset($result[$k]);
            } else if ($isMap) {
                $segs = explode(".", $k);
                $t = &$result;
                foreach ($segs as $seg) {
                    $t = &$t[$seg];
                }
                $t = $v;
                unset($t);
                unset($result[$k]);
            }
        }

        return $result;
    }
}