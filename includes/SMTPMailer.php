<?php
class SMTPMailer {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $debug = false;
    private $socket;

    public function __construct() {
        $this->host = SMTP_HOST;
        $this->port = SMTP_PORT;
        $this->user = SMTP_USER;
        $this->pass = SMTP_PASS;
    }

    public function send($to, $subject, $message) {
        try {
            if (!$this->connect()) return false;
            
            $server_name = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
            $this->sendCommand('EHLO ' . $server_name);
            
            if ($this->port == 587) {
                if (!$this->sendCommand('STARTTLS', 220)) return false;
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand('EHLO ' . $server_name);
            }
            
            if (!$this->sendCommand('AUTH LOGIN', 334)) return false;
            if (!$this->sendCommand(base64_encode($this->user), 334)) return false;
            if (!$this->sendCommand(base64_encode($this->pass), 235)) return false;
            
            if (!$this->sendCommand('MAIL FROM: <' . SMTP_FROM_EMAIL . '>', 250)) return false;
            if (!$this->sendCommand('RCPT TO: <' . $to . '>', 250)) return false;
            
            if (!$this->sendCommand('DATA', 354)) return false;
            
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
            $headers .= "To: <" . $to . ">\r\n";
            $headers .= "Subject: " . $subject . "\r\n";
            $headers .= "\r\n";
            
            fwrite($this->socket, $headers . $message . "\r\n.\r\n");
            $response = $this->readResponse();
            
            if (substr($response, 0, 3) != '250') {
                $this->logEmail($to, $subject, $message, 'failed', "SMTP Error: Response $response");
                return false;
            }
            
            $this->sendCommand('QUIT', 221);
            fclose($this->socket);
            
            $this->logEmail($to, $subject, $message, 'sent');
            return true;
            
        } catch (Exception $e) {
            $error = "SMTP Error: " . $e->getMessage();
            error_log($error);
            $this->logEmail($to, $subject, $message, 'failed', $error);
            return false;
        }
    }

    private function logEmail($to, $subject, $message, $status, $error = null) {
        try {
            // Asumimos que getDB() está disponible globalmente
            if (!function_exists('getDB')) {
                // Intento fallback básico o ignorar si no hay DB
                return;
            }
            $db = getDB();
            
            // Si db está cerrado o no es válido, intentamos reconectar
            if (!$db || $db->connect_error) {
                 return; 
            }

            $stmt = $db->prepare("INSERT INTO email_logs (recipient, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $to, $subject, $message, $status, $error);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }

    private function connect() {
        $protocol = ($this->port == 465) ? 'ssl://' : 'tcp://';
        $this->socket = fsockopen($protocol . $this->host, $this->port, $errno, $errstr, 15);
        
        if (!$this->socket) {
            error_log("Connection failed: $errno $errstr");
            return false;
        }
        
        $this->readResponse();
        return true;
    }

    private function sendCommand($cmd, $expectedCode = 250) {
        fwrite($this->socket, $cmd . "\r\n");
        $response = $this->readResponse();
        
        if (substr($response, 0, 3) != $expectedCode) {
            error_log("SMTP Error [$cmd]: $response");
            return false;
        }
        return true;
    }

    private function readResponse() {
        $response = '';
        while($str = fgets($this->socket, 515)) {
            $response .= $str;
            if(substr($str, 3, 1) == ' ') break;
        }
        return $response;
    }
}
?>
