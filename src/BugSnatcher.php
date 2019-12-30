<?php /** @noinspection ALL */

namespace KaiserWerk\BugSnatcher;

class BugSnatcher
{
    protected $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        
        set_error_handler([$this, "processError"], E_ALL);
        set_exception_handler([$this, "processException"]);
    }
    
    public function getConfiguration(): array
    {
        return $this->config; // You might need this eventually
    }
    
    private function processError(int $level = null, string $string = null, string $file = null, string $line = null, $exception = false): void
    {
        $error_data = "$string in file $file on line $line, PHP " . PHP_VERSION . " (" . PHP_OS . ")";
        $levels     = [
            E_ERROR             => 'Fatal runtime error',
            E_WARNING           => 'Warning',
            E_PARSE             => 'Parse error',
            E_NOTICE            => 'Notice',
            E_CORE_ERROR        => 'Core error',
            E_CORE_WARNING      => 'Core warning',
            E_COMPILE_ERROR     => 'Compile error',
            E_COMPILE_WARNING   => 'Compile warning',
            E_USER_ERROR        => 'User error',
            E_USER_WARNING      => 'User warning',
            E_USER_NOTICE       => 'User notice',
            E_STRICT            => 'Strict',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED        => 'Deprecation notice',
            E_USER_DEPRECATED   => 'User deprecation notice',
        ];
        
        if (!in_array($level, $levels)) {
            $level_prepared = $level;
        } else {
            $level_prepared = strtoupper($levels[$level]);
        }
        if ($exception === true) {
            $level_prepared = 'Exception ' . $level_prepared;
        }
        
        $log_line     = '[' . date("Y-m-d H:i:s") . '] [' . $_SERVER['REMOTE_ADDR'] . '] [' . $level_prepared . '] ' . trim($error_data);
        $log_line_raw = trim($error_data);
        
        $database = $this->config['database'];
        if ($database['enabled'] === true) {
            /**
             * error_level VARCHAR 50
             * error_string TEXT
             * error_file VARCHAR 512
             * error_line INT
             */
            if ($database['driver'] === 'mysql') {
                $pdo = new \PDO("mysql:host=" . $database['host'] . ";port=" . $database['port'] . ";charset=" . $database['charset'] . ";dbname=" . $database['dbname']);
                $stm = $pdo->prepare("INSERT INTO " . $database['table'] . " (error_level, error_string, error_file, error_line) VALUES (?, ?, ?, ?)");
                $stm->execute([
                    $level_prepared,
                    $string,
                    $file,
                    $line,
                ]);
            } elseif ($database['driver'] === 'sqlite') {
                @touch($database['path']); // create file if non-existant
                $pdo = new \PDO("sqlite:" . $database['path']);
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_errors (
                    id INTEGER PRIMARY KEY,
                    error_level VARCHAR(50),
                    error_string TEXT,
                    error_file VARCHAR(512),
                    error_line INT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )");
    
                $stm = $pdo->prepare("INSERT INTO " . $database['table'] . " (error_level, error_string, error_file, error_line) VALUES (?, ?, ?, ?)");
                $stm->execute([
                    $level_prepared,
                    $string,
                    $file,
                    $line,
                ]);
            }
        }
        
        $email = $this->config['email'];
        if ($email['enabled'] === true) {
            foreach ($email['mail_array'] as $address) {
                mail(
                    trim($address),
                    'Error: ' . $level_prepared . ' occured',
                    $log_line,
                    'From: ' . $this->config['general']['email_from']
                );
            }
        }
        
        $sms = $this->config['sms'];
        if ($sms['enabled'] === true) {
            $endpoint = sprintf('https://api.clockworksms.com/http/send.aspx?key=%s&to=%s&content=%s', $sms['apikey'], implode(',', $sms['sms_array']), urlencode($log_line));
            $result   = file_get_contents($endpoint); // use or log the result somehow?
        }
        
        $hipchat = $this->config['hipchat'];
        if ($hipchat['enabled'] === true) {
            $data = [
                'color'          => $hipchat['color'],
                'message'        => $log_line,
                'notify'         => false,
                'message_format' => 'text',
            ];
            
            $ch = curl_init(sprintf('https://%s.hipchat.com/v2/room/%s/notification?auth_token=%s', $hipchat['chatname'], $hipchat['room_no'], $hipchat['token']));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            $res = curl_exec($ch); // use the result somehow?
        }
        
        $logfile = $this->config['logfile'];
        if ($logfile['enabled'] === true) {
            $filename = $logfile['name'];
            $h        = @fopen($filename, 'a+');
            if ($h !== false) {
                
                @fwrite($h, $log_line . PHP_EOL);
                @fclose($h);
            } else {
                echo "nein";
            }
        }
        
        $logit = $this->config['logit'];
        if ($logit['enabled'] === true) {
            $ch = curl_init('https://log-it.codeforge.me/v2/write/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, [
                'X-Logit-Apikey' => $logit['apikey'],
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'set_id' => $logit['set_id'],
                'data'   => $log_line_raw,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
        
        $webhook = $this->config['webhook'];
        if ($webhook['enabled'] === true) {
            foreach ($webhook['hook_array'] as $hook) {
                $ch = curl_init($hook['url']);
                $optArray = array(
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => array(
                        'error_data' => $log_line_raw,
                    ),
                );
                if (isset($hook['no_ssl']) && $hook['no_ssl'] === true) {
                    $optArray[CURLOPT_SSL_VERIFYHOST] = false;
                    $optArray[CURLOPT_SSL_VERIFYPEER] = false;
                }
                curl_setopt_array($ch, $optArray);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }
    
    public function processException($exception)
    {
        $this->processError($exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine(), true);
    }
}