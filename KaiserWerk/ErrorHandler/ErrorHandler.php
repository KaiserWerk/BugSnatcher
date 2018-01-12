<?php

namespace KaiserWerk\ErrorHandler;

class ErrorHandler
{
    private $enabled = array();


    public static function getConfig()
    {
        return parse_ini_file(__DIR__ . '/ErrorHandlerConfiguration.ini', true, INI_SCANNER_TYPED);
    }

}