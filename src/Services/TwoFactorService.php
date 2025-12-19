<?php
/**
 * TwoFactorService
 * Implements TOTP (Time-Based One-Time Password) RFC 6238.
 * No external dependencies.
 */

class TwoFactorService {
    
    /**
     * Generate a new secret key (Base32 style)
     */
    public function generateSecret($length = 16): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Get the current One-Time Password
     */
    public function getCode(string $secret, ?int $timeSlice = null): string {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = $this->base32_decode($secret);

        // Pack time into 8 bytes (big endian)
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        
        // HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        
        // Dynamic Truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        
        $value = unpack('N', $hashPart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        
        $modulo = 10 ** 6;
        return str_pad((string)($value % $modulo), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a code
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool {
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate QR Code URL (for Google Authenticator usage via Google Charts API or similar)
     * Using a quick public API for rendering QR is easiest for vanilla PHP without libraries like BaconQrCode.
     * Caution: Sending secret to external API is a slight privacy risk, but standard for quick Implementations.
     * Better approach: Generate local JS QR.
     * For now, let's return a otpauth:// string and let the frontend render it (using a JS lib is safer).
     */
    public function getProvisioningUri(string $companyName, string $holder, string $secret): string {
        return "otpauth://totp/" . rawurlencode($companyName) . ":" . rawurlencode($holder) . "?secret=" . $secret . "&issuer=" . rawurlencode($companyName);
    }

    // Helper: Base32 Decode
    private function base32_decode($secret) {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedValues[$i] && 
                substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) {
                return false;
            }
        }
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = "";
        
        foreach ($secret as $char) {
            $char = strtoupper($char);
            if (!isset($base32charsFlipped[$char])) return false;
            $binaryString .= str_pad(decbin($base32charsFlipped[$char]), 5, '0', STR_PAD_LEFT);
        }
        
        $eightBits = str_split($binaryString, 8);
        $binary = "";
        
        foreach ($eightBits as $byte) {
             if (strlen($byte) < 8) break;
             $binary .= chr(bindec($byte));
        }
        
        return $binary;
    }
}
