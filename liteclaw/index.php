<?php
/**
 * LiteClaw - Interface Principale (Clone Claude AI)
 * Page d'index à la racine du projet
 */

require_once __DIR__ . '/config.php';
require_once CORE_PATH . '/auth.php';
require_once CORE_PATH . '/mistral.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

$pageTitle = 'LiteClaw - IA Autonome';
$models = MISTRAL_MODELS;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="dark-theme">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1 class="logo">LiteClaw</h1>
            <button class="new-chat-btn" onclick="startNewChat()">+ Nouvelle conversation</button>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="nav-item">
                <span class="icon">📜</span>
                Historique
            </a>
            <a href="#" class="nav-item">
                <span class="icon">🤖</span>
                Agents
            </a>
            <a href="#" class="nav-item">
                <span class="icon">⚙️</span>
                Paramètres
            </a>
        </nav>
        
        <?php if ($user): ?>
        <div class="sidebar-footer">
            <div class="user-info">
                <span class="avatar"><?= htmlspecialchars(strtoupper(substr($user['username'], 0, 2))) ?></span>
                <span class="username"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <a href="interface/logout.php" class="logout-btn">Déconnexion</a>
        </div>
        <?php else: ?>
        <div class="sidebar-footer">
            <a href="interface/login.php" class="login-btn">Connexion</a>
            <a href="interface/register.php" class="register-btn">Inscription</a>
        </div>
        <?php endif; ?>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Welcome Screen -->
            <div id="welcome-screen" class="welcome-screen">
                <h2>Bienvenue sur LiteClaw</h2>
                <p>Votre assistant IA autonome style Claude avec sélection intelligente de modèles</p>
                
                <div class="feature-cards">
                    <div class="feature-card">
                        <span class="feature-icon">🧠</span>
                        <h3>20 Modèles Mistral</h3>
                        <p>Code, Vision, Agent, Creative, Edge, Audio...</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🎯</span>
                        <h3>Sélection Auto</h3>
                        <p>L'IA choisit le meilleur modèle selon votre tâche</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">⚡</span>
                        <h3>3 Clés API</h3>
                        <p>Rotation automatique avec 1B tokens/mois chacune</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🔒</span>
                        <h3>Compatible Hostinger</h3>
                        <p>Optimisé pour hébergement mutualisé</p>
                    </div>
                </div>
            </div>
            
            <!-- Messages Area -->
            <div id="messages-area" class="messages-area" style="display: none;">
                <div id="messages-list"></div>
            </div>
            
            <!-- Input Area -->
            <div class="input-area">
                <div class="model-selector">
                    <select id="model-select">
                        <option value="auto" selected>🎯 Sélection automatique (recommandé)</option>
                        <optgroup label="Code & Développement">
                            <?php foreach ($models['code'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Flagship (Raisonnement)">
                            <?php foreach ($models['flagship'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Medium (Business)">
                            <?php foreach ($models['medium'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Small (Rapide)">
                            <?php foreach ($models['small'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Agent (Orchestration)">
                            <?php foreach ($models['agent'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Vision (Images)">
                            <?php foreach ($models['vision'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Creative">
                            <?php foreach ($models['creative'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Edge (Compact)">
                            <?php foreach ($models['edge'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Audio">
                            <?php foreach ($models['audio'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <div class="input-wrapper">
                    <textarea id="message-input" placeholder="Posez votre question ou décrivez une tâche... (Sélection auto recommandée)" rows="3"></textarea>
                    <div class="input-actions">
                        <button id="send-btn" class="send-btn">
                            <span>Envoyer</span>
                            <span>➤</span>
                        </button>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button class="quick-action" onclick="useTemplate('code')">💻 Générer du code</button>
                    <button class="quick-action" onclick="useTemplate('analyze')">🔍 Analyser</button>
                    <button class="quick-action" onclick="useTemplate('create')">✨ Créer</button>
                    <button class="quick-action" onclick="useTemplate('plan')">📋 Planifier</button>
                </div>
            </div>
        </div>
    </main>
    
    <script src="assets/script.js"></script>
</body>
</html>
