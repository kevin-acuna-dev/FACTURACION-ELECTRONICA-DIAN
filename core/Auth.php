<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Clase de autenticación
 * @method static void setUser(User $user) Establece el usuario autenticado manualmente
 */
class Auth {
    private static $user = null;
    
    /**
     * Establece el usuario autenticado manualmente
     * @param User $user Instancia del usuario
     * @return void
     */
    public static function setUser($user) {
        self::$user = $user;
    }
    
    public static function user() {
        if (self::$user !== null) {
            return self::$user;
        }
        
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }
        
        $db = Database::getInstance();
        $hashedToken = hash('sha256', $token);
        $sql = "SELECT u.* FROM users u 
                INNER JOIN personal_access_tokens pat ON pat.user_id = u.id 
                WHERE pat.token = ? AND pat.expires_at > NOW()";
        $userData = $db->fetchOne($sql, [$hashedToken]);
        
        if ($userData) {
            $user = new User();
            $user->hydrate($userData);
            self::$user = $user;
            return $user;
        }
        
        return null;
    }
    
    public static function id() {
        $user = self::user();
        return $user ? $user->id : null;
    }
    
    public static function check() {
        return self::user() !== null;
    }
    
    public static function getBearerToken() {
        if (php_sapi_name() === 'cli') {
            return null;
        }
        
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    public static function createToken($user) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        
        $db = Database::getInstance();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $sql = "INSERT INTO personal_access_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)";
        $db->query($sql, [$user->id, $hashedToken, $expiresAt]);
        
        return $token;
    }
    
    public static function revokeToken($token) {
        $hashedToken = hash('sha256', $token);
        $db = Database::getInstance();
        $sql = "DELETE FROM personal_access_tokens WHERE token = ?";
        $db->query($sql, [$hashedToken]);
    }
}

