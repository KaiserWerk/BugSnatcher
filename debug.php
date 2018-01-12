<?php


use KaiserWerk\ErrorHandler\ErrorHandler;

require_once 'vendor/autoload.php';
require_once 'src/ErrorHandler.php';

$eh = new ErrorHandler();
echo '<pre>';
#var_dump($eh->getConfig());

throw new \Exception('nope', 42);