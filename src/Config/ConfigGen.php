<?php

namespace Minimalism\Config;

use InvalidArgumentException;

/**
 * Class ConfigGen
 * GenConfigObject For IDE
 */
class ConfigGen
{

    /**
     * 将配置生成
     * @param array $conf
     * @param string $dir
     * @param string $clazz
     * @param string $namespace
     * @return mixed
     */
    public static function requireOnce(array $conf, $dir, $clazz = "ConfigObject", $namespace = __NAMESPACE__)
    {
        if (!static::isLegalVarName($clazz)) {
            throw new InvalidArgumentException("illegal class name \"$clazz\"");
        }

        static::genClassHelper($conf, $clazz, $clazzes);
        $header = "<?php" . PHP_EOL;
        $header .= <<<DOC
/**
 * Auto Generated by ConfigGen
 * !!! DO NOT EDIT UNLESS YOU ARE SURE THAT YOU KNOW WHAT YOU ARE DOING
 * @generated
 */

DOC;
        $header .= "namespace $namespace;";
        $body = implode(PHP_EOL . PHP_EOL, $clazzes);
        $footer = "return new $clazz;";
        $code = implode(PHP_EOL . PHP_EOL, [$header, $body, $footer]);
        $fTmpConf = "$dir/$clazz.php";
        file_put_contents($fTmpConf, $code);
        /** @noinspection PhpIncludeInspection */
        return require_once $fTmpConf;
    }

    protected static function isAllKeyString($var)
    {
        if (!is_array($var) || empty($var)) {
            return false;
        }
        foreach ($var as $k => $_) {
            if (!is_string($k)) {
                return false;
            }
        }
        return true;
    }

    protected static function isLegalVarName($varName)
    {
        return preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $varName);
    }

    protected static function formatVar($varName)
    {
        if (!static::isLegalVarName($varName)) {
            // throw new \RuntimeException("var name \"$varName\" is illegal");
            /** @noinspection PhpUnusedParameterInspection */
            $varName = preg_replace_callback("/([^a-zA-Z0-9_])/", function($matches) {
                static $i = 0;
                // TODO 汉子转拼音
                return "_" . ++$i . "_";
            }, $varName);
        }
        return $varName;
    }

    protected static function genClassHelper(array $conf, $clazz, &$clazzes = [])
    {
        if (empty($conf)) {
            return false;
        }

        $props = [];
        $ctorAssigns = [];

        foreach($conf as $k => $v) {
            $prop = static::formatVar($k);
            if (static::isAllKeyString($v)) {
                $subClazz = $clazz . "_" . static::formatVar($k);
                $props[] = "\tpublic \$$prop;";
                $ctorAssigns[] = "\t\t\$this->$prop = new $subClazz;";
                static::genClassHelper($v, $subClazz, $clazzes);
            } else {
                $props[] = "\tpublic \$$prop = " . var_export($v, true) . ";";
            }
        }

        $propsStr =  implode(PHP_EOL, $props);
        $ctorAssignsStr =  implode(PHP_EOL, $ctorAssigns);

        $clazzes[] = <<<TPL
final class $clazz {

$propsStr

\tpublic function __construct() {
$ctorAssignsStr
\t}
}
TPL;
        return true;
    }
}