<?php
/**
 * SimpleSMTP
 * A lightweight SMTP client for PHP, supporting TLS/SSL.
 * 
 * @package Services
 */

class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $timeout = 30;
    private $localhost = 'localhost';
    private $newline = "\r\n";
    private $socket = null;
    private $log = [];

    public function __construct(string $host, int $port, string $user, string $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send(string $from, string $fromName, string $to, string $subject, string $body, bool $isHtml = true): bool {
        try {
            $this->connect();
            $this->auth();
            
            $this->sendCommand('MAIL FROM: <' . $from . '>');
            $this->sendCommand('RCPT TO: <' . $to . '>');
            
            $this->sendCommand('DATA');
            
            // Build Headers
            $headers = [];
            $headers[] = "Date: " . date("r");
            $headers[] = "To: <$to>";
            $headers[] = "From: $fromName <$from>";
            $headers[] = "Subject: $subject";
            $headers[] = "MIME-Version: 1.0";
            if ($isHtml) {
                $headers[] = "Content-Type: text/html; charset=UTF-8";
            } else {
                $headers[] = "Content-Type: text/plain; charset=UTF-8";
            }
            $headers[] = ""; // End of headers

            $data = implode($this->newline, $headers) . $this->newline . $body . $this->newline . ".";
            $this->sendCommand($data);
            
            $this->sendCommand('QUIT');
            return true;

        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage() . " Log: " . json_encode($this->log));
            return false;
        } finally {
            if ($this->socket) {
                fclose($this->socket);
            }
        }
    }

    private function connect() {
        $protocol = '';
        if ($this->port == 465) $protocol = 'ssl://';
        // if ($this->port == 587) ... we start plain then STARTTLS

        $this->socket = fsockopen($protocol . $this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            throw new Exception("Could not connect to SMTP host: $errstr");
        }
        $this->getResponse();

        $this->sendCommand('EHLO ' . $this->localhost);

        if ($this->port == 587) {
            $this->sendCommand('STARTTLS');
            // Re-handshake
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand('EHLO ' . $this->localhost);
        }
    }

    private function auth() {
        if (!empty($this->user) && !empty($this->pass)) {
            $this->sendCommand('AUTH LOGIN');
            $this->sendCommand(base64_encode($this->user));
            $this->sendCommand(base64_encode($this->pass));
        }
    }

    private function sendCommand(string $cmd) {
        fputs($this->socket, $cmd . $this->newline);
        $this->getResponse();
    }

    private function getResponse() {
        $response = "";
        while($str = fgets($this->socket, 515)) {
             $response .= $str;
             if(substr($str, 3, 1) == " ") break;
        }
        $this->log[] = $response;
        // Basic error check
        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP Server reported error: $response");
        }
        return $response;
    }
}
