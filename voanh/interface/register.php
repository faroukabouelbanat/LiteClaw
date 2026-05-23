<?php
/**
 * VoAnh - Page d'Inscription
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/auth.php';

$auth = new Auth();
$error = '';

if ($auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Tous les champs sont requis';
    } elseif ($password !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide';
    } else {
        $result = $auth->register($username, $email, $password);
        
        if ($result['success']) {
            // Connexion automatique après inscription
            $loginResult = $auth->login($username, $password);
            if ($loginResult['success']) {
                header('Location: index.php?onboarding=1');
                exit;
            }
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - VoAnh</title>
    <link rel="stylesheet" href="/voanh/assets/styles.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            padding: 20px;
        }
        .auth-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .auth-box h1 {
            text-align: center;
            color: var(--accent);
            margin-bottom: 10px;
        }
        .auth-box p {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 15px;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .submit-btn:hover {
            background: var(--accent-hover);
        }
        .error-message {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .auth-links {
            text-align: center;
            margin-top: 25px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .auth-links a {
            color: var(--accent);
            text-decoration: none;
        }
        .features-list {
            list-style: none;
            margin: 20px 0;
            padding: 0;
        }
        .features-list li {
            padding: 8px 0;
            color: var(--text-secondary);
            font-size: 13px;
        }
        .features-list li:before {
            content: "✓ ";
            color: var(--success);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>VoAnh</h1>
            <p>Créez votre compte pour accéder à l'IA autonome</p>
            
            <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <ul class="features-list">
                    <li>Accès à 20+ modèles Mistral AI</li>
                    <li>Agents autonomes avec auto-renforcement</li>
                    <li>Mémoire évolutive (SOUL, PERSONALITY, LEARNING)</li>
                    <li>Génération de code et planification de tâches</li>
                </ul>
                
                <button type="submit" class="submit-btn">Créer un compte</button>
            </form>
            
            <div class="auth-links">
                Déjà un compte ? <a href="/voanh/interface/login.php">Se connecter</a>
            </div>
        </div>
    </div>
</body>
</html>
