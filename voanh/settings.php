<?php
/**
 * VoAnh - Page des Paramètres
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Nécessite une connexion
requireLogin();

$currentUser = getCurrentUser();
$userPreferences = getUserPreferences($currentUser['id']);

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $email = clean_input($_POST['email'] ?? '');
        $apiKey = trim($_POST['api_key'] ?? '');
        
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = updateUserProfile($currentUser['id'], [
                'email' => $email,
                'mistral_api_key' => !empty($apiKey) ? $apiKey : null
            ]);
            
            if ($result['success']) {
                $success = 'Profil mis à jour avec succès';
                // Mettre à jour les données courantes
                $currentUser = getCurrentUser();
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Email invalide';
        }
    } elseif ($action === 'update_preferences') {
        $defaultModel = $_POST['default_model'] ?? DEFAULT_MODEL;
        
        $result = updateUserPreferences($currentUser['id'], [
            'default_model' => $defaultModel,
            'notifications_enabled' => isset($_POST['notifications_enabled']) ? 1 : 0
        ]);
        
        if ($result['success']) {
            $success = 'Préférences mises à jour avec succès';
            $userPreferences = getUserPreferences($currentUser['id']);
        } else {
            $error = $result['error'];
        }
    } elseif ($action === 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($oldPassword) || empty($newPassword)) {
            $error = 'Veuillez remplir tous les champs de mot de passe';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Les nouveaux mots de passe ne correspondent pas';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères';
        } else {
            $result = changePassword($currentUser['id'], $oldPassword, $newPassword);
            
            if ($result['success']) {
                $success = 'Mot de passe changé avec succès';
            } else {
                $error = $result['error'];
            }
        }
    } elseif ($action === 'delete_account') {
        // Confirmation via token CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error = 'Token CSRF invalide';
        } else {
            // Supprimer toutes les sessions et messages de l'utilisateur
            $db = getDb();
            $db->query('DELETE FROM messages WHERE session_id IN (SELECT session_id FROM sessions WHERE user_id = ?)', [$currentUser['id']]);
            $db->query('DELETE FROM sessions WHERE user_id = ?', [$currentUser['id']]);
            $db->query('DELETE FROM cron_jobs WHERE user_id = ?', [$currentUser['id']]);
            $db->query('DELETE FROM users WHERE id = ?', [$currentUser['id']]);
            
            // Déconnexion
            logoutUser();
            redirect('index.php');
        }
    }
}

$csrfToken = generate_csrf_token();

// Récupérer la liste des modèles
global $MODELS;
$client = getMistralClient();
$availableModels = $client->listModels();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - VoAnh</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="settings-page">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1 class="logo">🦞 VoAnh</h1>
            <span class="subtitle">Paramètres</span>
        </div>
        
        <nav class="header-nav">
            <a href="index.php" class="nav-link">Chat</a>
            <a href="dashboard.php" class="nav-link">Tableau de bord</a>
            <a href="history.php" class="nav-link">Historique</a>
            <a href="settings.php" class="nav-link active">Paramètres</a>
            <a href="logout.php" class="nav-link btn-logout">Déconnexion</a>
        </nav>
    </header>

    <!-- Contenu Principal -->
    <main class="settings-content">
        <div class="settings-container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Profil -->
            <div class="card">
                <h2>👤 Modifier le profil</h2>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="api_key">Clé API Mistral personnelle (optionnel)</label>
                        <input type="text" id="api_key" name="api_key" 
                               placeholder="Entrez votre clé API Mistral (sans sk-)"
                               value="<?php echo htmlspecialchars($currentUser['mistral_api_key'] ?? ''); ?>">
                        <small>Votre clé sera utilisée en priorité avant les clés partagées. Format: caractères alphanumériques.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>

            <!-- Préférences -->
            <div class="card">
                <h2>⚙️ Préférences</h2>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="form-group">
                        <label for="default_model">Modèle par défaut</label>
                        <select id="default_model" name="default_model">
                            <?php foreach ($availableModels as $modelId => $modelInfo): ?>
                                <option value="<?php echo htmlspecialchars($modelId); ?>"
                                        <?php echo ($userPreferences['default_model'] ?? DEFAULT_MODEL) === $modelId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($modelId); ?> - <?php echo htmlspecialchars($modelInfo['description']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="notifications_enabled" 
                                   <?php echo ($userPreferences['notifications_enabled'] ?? 1) ? 'checked' : ''; ?>>
                            Activer les notifications
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>

            <!-- Changer le mot de passe -->
            <div class="card">
                <h2>🔒 Changer le mot de passe</h2>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="old_password">Mot de passe actuel</label>
                        <input type="password" id="old_password" name="old_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                </form>
            </div>

            <!-- Zone de danger -->
            <div class="card danger-zone">
                <h2>⚠️ Zone de danger</h2>
                <p>Ces actions sont irréversibles. Soyez certain de votre choix.</p>
                
                <form method="POST" class="settings-form" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer votre compte ? Toutes vos données seront perdues.');">
                    <input type="hidden" name="action" value="delete_account">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    
                    <button type="submit" class="btn btn-danger">Supprimer mon compte</button>
                </form>
            </div>
        </div>
    </main>

    <style>
        .settings-content {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
        }
        
        .card h2 {
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
        }
        
        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
            cursor: pointer;
        }
        
        select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .danger-zone {
            border-color: var(--danger-color);
        }
        
        .danger-zone h2 {
            color: var(--danger-color);
        }
        
        .danger-zone p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
    </style>
</body>
</html>
