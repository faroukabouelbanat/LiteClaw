<?php
/**
 * LiteClaw - Système d'Authentification
 * Compatible hébergement mutualisé Hostinger
 */

require_once __DIR__ . '/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($username, $email, $password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères'];
        }
        
        $existing = $this->db->fetch("SELECT id FROM users WHERE username = :username OR email = :email", [
            ':username' => $username,
            ':email' => $email
        ]);
        
        if ($existing) {
            return ['success' => false, 'error' => 'Ce nom d\'utilisateur ou cet email existe déjà'];
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $userId = $this->db->insert('users', [
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash
            ]);
            
            $this->logActivity($userId, 'register');
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            liteclaw_log("Registration failed: " . $e->getMessage(), 1);
            return ['success' => false, 'error' => 'Erreur lors de l\'inscription'];
        }
    }
    
    public function login($username, $password) {
        $user = $this->db->fetch("SELECT * FROM users WHERE username = :username OR email = :username", [
            ':username' => $username
        ]);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Identifiants invalides'];
        }
        
        // Vérifier si le compte est verrouillé
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $remaining = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['success' => false, 'error' => "Compte verrouillé pour $remaining minutes"];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            // Incrémenter les tentatives
            $attempts = $user['login_attempts'] + 1;
            $lockedUntil = null;
            
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
                $attempts = 0;
            }
            
            $this->db->update('users', [
                ':login_attempts' => $attempts,
                ':locked_until' => $lockedUntil
            ], 'id = :id', [':id' => $user['id']]);
            
            return ['success' => false, 'error' => 'Identifiants invalides'];
        }
        
        // Réinitialiser les tentatives
        $this->db->update('users', [
            ':login_attempts' => 0,
            ':locked_until' => null,
            ':last_login' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $user['id']]);
        
        // Créer une session
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
        $this->db->insert('sessions', [
            ':user_id' => $user['id'],
            ':token' => $token,
            ':expires_at' => $expiresAt,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_token'] = $token;
        $_SESSION['username'] = $user['username'];
        
        $this->logActivity($user['id'], 'login');
        
        return ['success' => true, 'user' => $user];
    }
    
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            $this->db->delete('sessions', 'token = :token', [':token' => $_SESSION['session_token']]);
        }
        
        session_destroy();
        session_start();
        
        return ['success' => true];
    }
    
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        $session = $this->db->fetch(
            "SELECT * FROM sessions WHERE token = :token AND user_id = :user_id AND expires_at > datetime('now')",
            [':token' => $_SESSION['session_token'], ':user_id' => $_SESSION['user_id']]
        );
        
        if (!$session) {
            return false;
        }
        
        // Mettre à jour l'activité
        $this->db->update('sessions', [':last_activity' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $session['id']]);
        
        return true;
    }
    
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $this->db->fetch("SELECT * FROM users WHERE id = :id", [':id' => $_SESSION['user_id']]);
    }
    
    public function updateApiKey($userId, $apiKey) {
        $this->db->update('users', [':mistral_api_key' => $apiKey], 'id = :id', [':id' => $userId]);
        $this->logActivity($userId, 'update_api_key');
        return ['success' => true];
    }
    
    private function logActivity($userId, $action, $details = null) {
        $this->db->insert('activity_logs', [
            ':user_id' => $userId,
            ':action' => $action,
            ':details' => $details ? json_encode($details) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
}
