<?php
namespace KaiserWerk\ErrorHandler;

#use KaiserWerk\ErrorHandler\ErrorHandler;

require_once 'src/ErrorHandler.php';

$eh = new ErrorHandler();
echo '<pre>';
var_dump($eh::getConfig());