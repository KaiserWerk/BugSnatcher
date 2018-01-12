<?php

namespace KaiserWerk\ErrorHandler;

class ErrorHandler
{
    protected $config;
    public function __construct()
    {
        $this->config = parse_ini_file(__DIR__ . '/ErrorHandlerConfiguration.ini', true, INI_SCANNER_TYPED);
        set_error_handler(array($this, 'errorHandler'));
        set_exception_handler(array($this, 'exceptionHandler'));
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function errorHandler($level, $string, $file, $line)
    {
        // 5th parameter context is deprecated as of PHP 7.2,
        // ergo it won't be used

        if (!(error_reporting() & $level)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }
        $this->processError($level, $string, $file, $line);

        /* Don't execute PHP internal error handler */
        return true;
    }

    public function exceptionHandler($exception)
    {
        $this->processError($exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine(), true);
    }

    private function processError($level = null, $string = null, $file = null, $line = null, $exception = false)
    {
        $error_data = "$string in file $file on line $line, PHP " . PHP_VERSION . " (" . PHP_OS . ")";
        $levels = array(
            E_ERROR => 'Fatal runtime error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core error',
            E_CORE_WARNING => 'Core warning',
            E_COMPILE_ERROR => 'Compile error',
            E_COMPILE_WARNING => 'Compile warning',
            E_USER_ERROR => 'User error',
            E_USER_WARNING => 'User warning',
            E_USER_NOTICE => 'User notice',
            E_STRICT => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED => 'Deprecation notice',
            E_USER_DEPRECATED => 'User deprecation notice',
        );

        if (!in_array($level, $levels)) {
            $level_prepared = $level;
        } else {
            $level_prepared = strtoupper($levels[$level]);
        }
        if ($exception === true) {
            $level_prepared = 'Exception '.$level_prepared;
        }

        $log_line = '['.date("Y-m-d H:i:s").'] [' . $_SERVER['REMOTE_ADDR'] . '] [' . $level_prepared . '] ' . trim($error_data);
        $log_line_raw = trim($error_data);

        $database = $this->config['database'];
        if ($database['enabled'] === true) {
            // create schema
            //
            //
            //
        }

        $email = $this->config['email'];
        if ($email['enabled'] === true) {
            foreach ($email['mail_array'] as $address)
            mail(
                $address,
                'Error: '.$level_prepared.' occured',
                $log_line,
                'From: '.$this->config['general']['email_from']
            );
        }

        $sms = $this->config['sms'];
        if ($sms['enabled'] === true) {
            $endpoint = sprintf('https://api.clockworksms.com/http/send.aspx?key=%s&to=%s&content=%s', $sms['apikey'], implode(',', $sms['sms_array']), urlencode($log_line));
            $result = file_get_contents($endpoint); // use or log the result somehow?
        }

        $hipchat = $this->config['hipchat'];
        if ($hipchat['enabled'] === true) {
            $data = array(
                'color' => $hipchat['color'],
                'message' => $log_line,
                'notify' => false,
                'message_format' => 'text',
            );

            $ch = curl_init(sprintf('https://%s.hipchat.com/v2/room/%s/notification?auth_token=%s', $hipchat['chatname'], $hipchat['room_no'], $hipchat['token']));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
            $res = curl_exec($ch); // use the result somehow?
        }

        $logfile = $this->config['logfile'];
        if ($logfile['enabled'] === true) {
            $filename = $logfile['name'];
            $h = @fopen($filename, 'a+');
            if ($h !== false) {

                @fwrite($h, $log_line . PHP_EOL);
                @fclose($h);
            } else {
                echo "nein";
            }
        }

        $logit = $this->config['logit'];
        if ($logit['enabled'] === true) {
            $ch = curl_init('https://api.log-it.me/v2/write/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, array(
                'X-Logit-ApiKey' => $logit['apikey'],
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, array(
                'set_id' => $logit['set_id'],
                'data' => $log_line_raw,
            ));
            curl_exec($ch);
            curl_close($ch);
        }

        $webhook = $this->config['webhook'];
        if ($webhook['enabled'] === true) {
            echo "ja";
        }
    }

    private function SMTPsend ($to, $from, $subject, $body)
    {   define (LF, "\n");              // Normal linefeed
        define (CRLF, "\r\n");       // Always used by SMTP.

        if ($_SERVER["SERVER_ADDR"])
            define (BR, "<br />");       // HTML linefeed
        else
            define (BR, LF);                // Normal linefeed

//    Build header lines. Remember TWO linefeeds at the end!
        $header = <<<headend
To: $to
From: $from
Subject: $subject
MIME-Version: 1.0
Content-Type: text/plain; charset=utf-8; format=flowed
Content-Transfer-Encoding: 7bit
X-Priority: 3
X-Mailer: SMTPsend 2.0


headend;

        $smtp_host = "ssl://mail.whatever.net";   // Change these!
        $smtp_port = 465;
        $smtp_user = "username+whatever.net";
        $smtp_pass = "My SeCrEt PaSsWoRd";

// Only addresses, with brackets
        if ( ($p = strpos($to,"<") ) > 0)
            $smtp_to = substr ($to,$p);
        else
            $smtp_to = "<$to>";

        if ( ($p = strpos($from,"<") ) > 0)
            $smtp_from = substr ($from,$p);
        else
            $smtp_from = "<$from>";

//    Open socket
        $smtp_server = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30)
        or die("Attempting connection to $smtp_host on port $smtp_port failed!"
            .BR."Connection refused on $smtp_host at port $smtp_port.");

        if(!$smtp_server)
        {    error_log ("Sending to ".$smtp_host." failed!".LF,3,ERRORLOG);
            print "Connection to $smtp_host on port $smtp_port failed!"
                .BR."[$errorno] $errorstr";
            return false;
        }

        fwrite($smtp_server,
            "EHLO".CRLF
            ."MAIL FROM:$smtp_from".CRLF
            ."RCPT TO:$smtp_to".CRLF
            ."DATA".CRLF);

        fwrite($smtp_server, $header);

        $max = 2048;
        $start = $end = 0;

        echo "Body length: ".strlen($body).BR;
        do
        {    $end = min (strlen($body),$start+$max);
            $len = $end-$start;
//        echo "Start = $start, End = $end".BR;
            fwrite($smtp_server, substr ($body,$start,$len));
            $start += $len;
        } while ($start < strlen($body));

        fwrite($smtp_server, CRLF.CRLF.".".CRLF."QUIT".CRLF);
        fclose($smtp_server);
        error_log ("Sending complete.".LF,3,ERRORLOG);
        return true;
    }
}