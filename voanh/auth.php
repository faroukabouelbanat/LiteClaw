<?php
/**
 * VoAnh - Authentification Utilisateur
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Inscrit un nouvel utilisateur
 */
function registerUser($email, $password) {
    $db = getDb();
    
    // Vérifier si l'email existe déjà
    $existing = $db->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    
    if ($existing) {
        return ['success' => false, 'error' => 'Cet email est déjà enregistré'];
    }
    
    // Hacher le mot de passe
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $userId = $db->insert('users', [
            'email' => $email,
            'password_hash' => $passwordHash
        ]);
        
        // Créer les préférences par défaut
        $db->insert('user_preferences', [
            'user_id' => $userId,
            'default_model' => DEFAULT_MODEL,
            'notifications_enabled' => 1
        ]);
        
        return ['success' => true, 'user_id' => $userId];
        
    } catch (Exception $e) {
        log_error('Erreur inscription: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Erreur lors de l\'inscription'];
    }
}

/**
 * Connecte un utilisateur
 */
function loginUser($email, $password) {
    $db = getDb();
    
    $user = $db->fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
    
    if (!$user) {
        return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
    }
    
    if (!$user['is_active']) {
        return ['success' => false, 'error' => 'Compte désactivé'];
    }
    
    // Démarrer la session utilisateur
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    
    return ['success' => true, 'user' => $user];
}

/**
 * Déconnecte l'utilisateur
 */
function logoutUser() {
    session_destroy();
    return true;
}

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Obtient l'utilisateur actuel
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDb();
    return $db->fetchOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
}

/**
 * Met à jour le profil utilisateur
 */
function updateUserProfile($userId, $data) {
    $db = getDb();
    
    $allowedFields = ['mistral_api_key', 'email'];
    $updateData = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }
    
    if (empty($updateData)) {
        return ['success' => false, 'error' => 'Aucune donnée à mettre à jour'];
    }
    
    $db->update('users', $updateData, 'id = ?', [$userId]);
    $db->update('users', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
    
    return ['success' => true];
}

/**
 * Change le mot de passe
 */
function changePassword($userId, $oldPassword, $newPassword) {
    $db = getDb();
    
    $user = $db->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);
    
    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Mot de passe actuel incorrect'];
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->update('users', ['password_hash' => $newHash], 'id = ?', [$userId]);
    
    return ['success' => true];
}

/**
 * Obtient les préférences utilisateur
 */
function getUserPreferences($userId) {
    $db = getDb();
    return $db->fetchOne('SELECT * FROM user_preferences WHERE user_id = ?', [$userId]);
}

/**
 * Met à jour les préférences utilisateur
 */
function updateUserPreferences($userId, $preferences) {
    $db = getDb();
    
    $db->update('user_preferences', $preferences, 'user_id = ?', [$userId]);
    
    return ['success' => true];
}

// Fonction helper pour vérifier la connexion et rediriger
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}
