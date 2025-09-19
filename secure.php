<?php
/**
 * Secure Session Encryption Wrapper
 * 
 * This file provides session encryption without replacing the default session handler
 */

class SessionEncryptor {
    private static $key;
    private static $cipher = 'aes-256-cbc';
    private static $initialized = false;
    
    /**
     * Initialize the encryption
     */
    public static function init() {
        // Prevent multiple initializations
        if (self::$initialized) {
            return;
        }
        
        // Get encryption key from config or environment
        if (defined('SESSION_ENCRYPTION_KEY')) {
            self::$key = SESSION_ENCRYPTION_KEY;
        } elseif (getenv('SESSION_ENCRYPTION_KEY')) {
            self::$key = getenv('SESSION_ENCRYPTION_KEY');
        } else {
            // Fallback to a generated key (not recommended for production)
            self::$key = hash('sha256', 'default_fallback_key_please_change', true);
        }
        
        // Ensure key is exactly 32 bytes
        if (strlen(self::$key) < 32) {
            self::$key = str_pad(self::$key, 32, "\0");
        } elseif (strlen(self::$key) > 32) {
            self::$key = substr(self::$key, 0, 32);
        }
        
        // Start session with enhanced security settings only if not already started
        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
            
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $isSecure,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true
            ]);
            
            // Decrypt existing session data
            self::decryptSession();
        } else {
            // Session already started, just decrypt the data
            self::decryptSession();
        }
        
        self::$initialized = true;
    }
    
    /**
     * Encrypt session data before writing
     */
    public static function encryptSession() {
        // Skip encryption if session is empty or already encrypted
        if (empty($_SESSION) || (isset($_SESSION['_encrypted']) && $_SESSION['_encrypted'] === true)) {
            return;
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt(serialize($_SESSION), self::$cipher, self::$key, 0, $iv);
        
        if ($encrypted !== false) {
            // Store the encrypted data
            $_SESSION['_encrypted_data'] = base64_encode($iv . $encrypted);
            $_SESSION['_encrypted'] = true;
            
            // Keep only the encryption markers in the session
            $tempData = $_SESSION['_encrypted_data'];
            $tempFlag = $_SESSION['_encrypted'];
            $_SESSION = [];
            $_SESSION['_encrypted_data'] = $tempData;
            $_SESSION['_encrypted'] = $tempFlag;
        }
    }
    
    /**
     * Decrypt session data after reading
     */
    public static function decryptSession() {
        if (isset($_SESSION['_encrypted']) && $_SESSION['_encrypted'] === true && isset($_SESSION['_encrypted_data'])) {
            $data = base64_decode($_SESSION['_encrypted_data']);
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            $decrypted = openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
            
            if ($decrypted !== false) {
                $sessionData = unserialize($decrypted);
                if ($sessionData !== false) {
                    // Store encryption markers temporarily
                    $encryptedData = $_SESSION['_encrypted_data'];
                    $encryptedFlag = $_SESSION['_encrypted'];
                    
                    // Restore decrypted session data
                    $_SESSION = $sessionData;
                    
                    // Restore encryption markers
                    $_SESSION['_encrypted_data'] = $encryptedData;
                    $_SESSION['_encrypted'] = $encryptedFlag;
                }
            }
        }
    }
}

// Automatically initialize session encryption
SessionEncryptor::init();

// Re-encrypt session on shutdown
register_shutdown_function([SessionEncryptor::class, 'encryptSession']);
?>