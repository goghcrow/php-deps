<?php

namespace Minimalism\Test;

use Minimalism\Config\Converter;
use Minimalism\Config\Yaconf;

require __DIR__ . "/../../src/Config/Config.php";
require __DIR__ . "/../../src/Config/Yaconf.php";
require __DIR__ . "/../../src/Config/Converter.php";


///*
$converter = new Converter(__DIR__ . "/config");
echo $converter->scanDir($converter->basedir);

//$ini = $converter->scanDir($converter->basedir)->__toString();
//$r = Yaconf::parse($ini);
//echo json_encode($r, JSON_PRETTY_PRINT);
//*/

/*
$converter = new Converter(__DIR__ . "/ExampleConfig/dev");
echo $converter->scanDir($converter->basedir);

$ini = $converter->scanDir($converter->basedir)->__toString();
$r = Yaconf::parse($ini);
echo json_encode($r, JSON_PRETTY_PRINT);
*/