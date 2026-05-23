<?php
/**
 * VoAnh - Système d'Authentification
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
            $this->db->beginTransaction();
            
            $userId = $this->db->insert('users', [
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash
            ]);
            
            // Initialiser la mémoire SOUL
            $this->db->insert('soul_memory', [
                ':user_id' => $userId,
                ':identity_profile' => json_encode(['created' => date('Y-m-d H:i:s')]),
                ':core_values' => '[]',
                ':personality_traits' => '[]'
            ]);
            
            // Initialiser les autres mémoires
            $this->db->insert('personality_memory', [
                ':user_id' => $userId,
                ':communication_style' => '{}',
                ':response_preferences' => '{}',
                ':tone_settings' => '{}'
            ]);
            
            $this->db->insert('subconscious_memory', [
                ':user_id' => $userId,
                ':implicit_patterns' => '{}',
                ':behavioral_tendencies' => '{}',
                ':learned_associations' => '{}',
                ':confidence_scores' => '{}'
            ]);
            
            $this->db->insert('learning_memory', [
                ':user_id' => $userId,
                ':knowledge_base' => '{}',
                ':skills_acquired' => '[]',
                ':corrections_applied' => '[]',
                ':success_patterns' => '[]'
            ]);
            
            // Créer les étapes d'onboarding
            $onboardingSteps = [
                ['welcome', 1],
                ['setup_profile', 2],
                ['configure_api', 3],
                ['first_conversation', 4],
                ['explore_agents', 5],
                ['create_task', 6]
            ];
            
            foreach ($onboardingSteps as $step) {
                $this->db->insert('onboarding_steps', [
                    ':user_id' => $userId,
                    ':step_name' => $step[0],
                    ':step_order' => $step[1]
                ]);
            }
            
            $this->db->commit();
            
            $this->logActivity($userId, 'register');
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            $this->db->rollback();
            voanh_log("Registration failed: " . $e->getMessage(), 1);
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
    
    public function updatePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetch("SELECT * FROM users WHERE id = :id", [':id' => $userId]);
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Mot de passe actuel incorrect'];
        }
        
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'error' => 'Le nouveau mot de passe est trop court'];
        }
        
        $this->db->update('users', [
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', [':id' => $userId]);
        
        $this->logActivity($userId, 'change_password');
        
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
    
    public function getOnboardingStatus($userId) {
        return $this->db->fetchAll(
            "SELECT * FROM onboarding_steps WHERE user_id = :user_id ORDER BY step_order",
            [':user_id' => $userId]
        );
    }
    
    public function completeOnboardingStep($userId, $stepName, $data = []) {
        $this->db->update('onboarding_steps', [
            ':completed' => 1,
            ':completed_at' => date('Y-m-d H:i:s'),
            ':data' => json_encode($data)
        ], 'user_id = :user_id AND step_name = :step_name', [
            ':user_id' => $userId,
            ':step_name' => $stepName
        ]);
        
        // Vérifier si tous les steps sont complétés
        $remaining = $this->db->fetch(
            "SELECT COUNT(*) as count FROM onboarding_steps WHERE user_id = :user_id AND completed = 0",
            [':user_id' => $userId]
        );
        
        if ($remaining['count'] == 0) {
            $this->db->update('users', [':onboarding_completed' => 1], 'id = :id', [':id' => $userId]);
        }
        
        return ['success' => true];
    }
}
