<?php
/**
 * VoAnh - Page de Connexion
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/auth.php';

$auth = new Auth();
$error = '';
$success = '';

// Si déjà connecté, rediriger
if ($auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            header('Location: index.php');
            exit;
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
    <title>Connexion - VoAnh</title>
    <link rel="stylesheet" href="/voanh/assets/styles.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
        }
        .auth-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .auth-box h1 {
            text-align: center;
            color: var(--accent);
            margin-bottom: 30px;
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
            margin-top: 20px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        .auth-links a {
            color: var(--accent);
            text-decoration: none;
        }
        .auth-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>VoAnh</h1>
            
            <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur ou Email</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="submit-btn">Se connecter</button>
            </form>
            
            <div class="auth-links">
                Pas encore de compte ? <a href="/voanh/interface/register.php">S'inscrire</a>
            </div>
        </div>
    </div>
</body>
</html>
