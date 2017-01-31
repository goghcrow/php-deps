<?php

namespace Minimalism\Scheme\Test;

use Minimalism\Scheme\Interpreter;
use Minimalism\Scheme\Value\Type;
use Minimalism\Scheme\Value\Value;

require_once __DIR__ . "/../vendor/autoload.php";

$interp = new Interpreter();


$f = __DIR__ . "/cases/curry";
$value = $interp->interp($f);
exit($value === Value::$VOID ? 0 : $value);