<?php
class JWT {
    private static $secret_key = "your_secret_key_here_school_hr_system";
    private static $encrypt_method = "HS256";

    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60 * 24); // 24 hours
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::secret(), true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    /**
     * The signing key. Set a private one per server in config/jwt_secret.local.php
     * (`<?php return 'long random string';`, not in git) or the SKOOL_JWT_SECRET
     * environment variable; the built-in key is public in the repository.
     */
    private static function secret() {
        static $secret = null;
        if ($secret === null) {
            $local = __DIR__ . '/jwt_secret.local.php';
            $fromFile = is_file($local) ? include $local : null;
            $fromEnv = getenv('SKOOL_JWT_SECRET');
            $secret = is_string($fromFile) && strlen($fromFile) >= 32 ? $fromFile
                : (is_string($fromEnv) && strlen($fromEnv) >= 32 ? $fromEnv : self::$secret_key);
        }
        return $secret;
    }

    /** Payload of a correctly signed, unexpired token; false otherwise. */
    public static function decode($jwt) {
        if (!is_string($jwt) || !self::verify($jwt)) {
            return false;
        }
        $tokenParts = explode('.', $jwt);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);

        if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }

    public static function verify($jwt) {
        $tokenParts = explode('.', (string)$jwt);
        if (count($tokenParts) != 3) {
            return false;
        }

        $signature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], self::secret(), true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return hash_equals($base64Signature, $tokenParts[2]);
    }
}
?>
