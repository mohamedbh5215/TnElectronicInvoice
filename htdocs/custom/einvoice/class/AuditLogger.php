<?php

class AuditLogger {
    private $logFile;
    private $maxFileSize;
    private $encryptionKey;
    private $hashChain;

    public function __construct($logFile, $maxFileSize = 10485760, $encryptionKey) {
        $this->logFile = $logFile;
        $this->maxFileSize = $maxFileSize;
        $this->encryptionKey = $encryptionKey;
        $this->hashChain = '';
        $this->initializeLogFile();
    }

    private function initializeLogFile() {
        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    private function encrypt($data) {
        return openssl_encrypt($data, 'aes-256-cbc', $this->encryptionKey, 0, str_repeat('\0', 16));
    }

    private function writeLog($message) {
        $encryptedMessage = $this->encrypt($message);
        file_put_contents($this->logFile, $encryptedMessage . PHP_EOL, FILE_APPEND);
        $this->hashChain = hash('sha256', $this->hashChain . $encryptedMessage);
        $this->checkFileRotation();
    }

    private function checkFileRotation() {
        if (filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateFile();
        }
    }

    private function rotateFile() {
        rename($this->logFile, $this->logFile . '.' . time());
        $this->initializeLogFile();
    }

    public function logAction($action) {
        $this->writeLog('Action: ' . $action);
    }

    public function logSignature($signature) {
        $this->writeLog('Signature: ' . $signature);
    }

    public function logStateChange($state) {
        $this->writeLog('State changed to: ' . $state);
    }

    public function logAPICall($endpoint) {
        $this->writeLog('API Call made to: ' . $endpoint);
    }

    public function logError($error) {
        $this->writeLog('Error: ' . $error);
    }

    public function logWarning($warning) {
        $this->writeLog('Warning: ' . $warning);
    }

    public function logInfo($info) {
        $this->writeLog('Info: ' . $info);
    }

    public function getInvoiceAuditTrail($years = 10) {
        $trail = [];
        $limitDate = strtotime('-' . $years . ' years');
        foreach (file($this->logFile) as $line) {
            $decryptedMessage = openssl_decrypt(trim($line), 'aes-256-cbc', $this->encryptionKey, 0, str_repeat('\0', 16));
            if ($decryptedMessage) {
                $trail[] = $decryptedMessage;
            }
        }
        return array_filter($trail, function($entry) use ($limitDate) {
            return strtotime($entry['timestamp']) >= $limitDate;
        });
    }

    public function exportAuditLog($format = 'json') {
        // Implementation for JSON/CSV export goes here.
    }
}

// Usage example:
//$logger = new AuditLogger('path/to/audit.log', 10485760, 'your-encryption-key');
//$logger->logAction('User logged in.');
?>