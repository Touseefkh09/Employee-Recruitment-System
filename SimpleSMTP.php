<?php
class SimpleSMTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $lastError = '';

    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $body, $fromName = 'Employee Recruitment System') {
        try {
            $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
            if (!$this->socket) {
                $this->lastError = "Could not connect to SMTP host: $errstr ($errno)";
                return false;
            }

            if (!$this->checkResponse($this->read(), '220')) {
                return false;
            }
            
            if (!$this->cmd("EHLO " . gethostname(), '250')) {
                return false;
            }
            
            if (!$this->cmd("AUTH LOGIN", '334')) {
                return false;
            }
            
            if (!$this->cmd(base64_encode($this->username), '334')) {
                return false;
            }
            
            if (!$this->cmd(base64_encode($this->password), '235')) {
                return false;
            }
            
            if (!$this->cmd("MAIL FROM: <" . $this->username . ">", '250')) {
                return false;
            }
            
            if (!$this->cmd("RCPT TO: <$to>", '250')) {
                return false;
            }
            
            if (!$this->cmd("DATA", '354')) {
                return false;
            }

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: $fromName <" . $this->username . ">\r\n";
            $headers .= "To: $to\r\n";
            $headers .= "Subject: $subject\r\n";

            if (!$this->cmd($headers . "\r\n" . $body . "\r\n.", '250')) {
                return false;
            }
            
            $this->cmd("QUIT", '221');
            fclose($this->socket);
            
            return true;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if ($this->socket) {
                fclose($this->socket);
            }
            return false;
        }
    }

    private function cmd($command, $expectedCode = null) {
        fputs($this->socket, $command . "\r\n");
        $response = $this->read();
        
        if ($expectedCode !== null) {
            return $this->checkResponse($response, $expectedCode);
        }
        
        return $response;
    }

    private function read() {
        $response = "";
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") { break; }
        }
        return $response;
    }
    
    private function checkResponse($response, $expectedCode) {
        $code = substr($response, 0, 3);
        if ($code != $expectedCode) {
            $this->lastError = "SMTP Error: Expected $expectedCode, got $code - $response";
            return false;
        }
        return true;
    }
    
    public function getLastError() {
        return $this->lastError;
    }
}
?>
