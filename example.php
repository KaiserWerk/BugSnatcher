<?php


use KaiserWerk\BugSnatcher\BugSnatcher;

require_once 'vendor/autoload.php';
require_once 'src/BugSnatcher.php';

$eh = new BugSnatcher();
echo '<pre>';
#var_dump($eh->getConfig());
// Just throw an exception and see what happens
throw new \Exception('nope', 42);