<?php
/**
 * VoAnh - Interface Principale (Clone Claude AI)
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/auth.php';
require_once CORE_PATH . '/mistral.php';
require_once CORE_PATH . '/agent.php';
require_once CORE_PATH . '/memory.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

// Redirection vers login si non authentifié
if (!$user && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'register.php') {
    // Pour l'instant, on permet l'accès pour tester
    // header('Location: login.php');
    // exit;
}

$pageTitle = 'VoAnh - IA Autonome';
$models = MISTRAL_MODELS;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/voanh/assets/styles.css">
</head>
<body class="dark-theme">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1 class="logo">VoAnh</h1>
            <button class="new-chat-btn" onclick="startNewChat()">+ Nouvelle conversation</button>
        </div>
        
        <nav class="sidebar-nav">
            <a href="/voanh/interface/history.php" class="nav-item">
                <span class="icon">📜</span>
                Historique
            </a>
            <a href="/voanh/interface/dashboard.php" class="nav-item">
                <span class="icon">📊</span>
                Tableau de bord
            </a>
            <a href="/voanh/interface/agents.php" class="nav-item">
                <span class="icon">🤖</span>
                Agents
            </a>
            <a href="/voanh/interface/tasks.php" class="nav-item">
                <span class="icon">✅</span>
                Tâches
            </a>
            <a href="/voanh/interface/settings.php" class="nav-item">
                <span class="icon">⚙️</span>
                Paramètres
            </a>
        </nav>
        
        <?php if ($user): ?>
        <div class="sidebar-footer">
            <div class="user-info">
                <span class="avatar"><?= strtoupper(substr($user['username'], 0, 2)) ?></span>
                <span class="username"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <a href="/voanh/interface/logout.php" class="logout-btn">Déconnexion</a>
        </div>
        <?php else: ?>
        <div class="sidebar-footer">
            <a href="/voanh/interface/login.php" class="login-btn">Connexion</a>
            <a href="/voanh/interface/register.php" class="register-btn">Inscription</a>
        </div>
        <?php endif; ?>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Chat Container -->
        <div class="chat-container">
            <!-- Welcome Screen -->
            <div id="welcome-screen" class="welcome-screen">
                <h2>Bienvenue sur VoAnh</h2>
                <p>Votre assistant IA autonome avec auto-renforcement</p>
                
                <div class="feature-cards">
                    <div class="feature-card">
                        <span class="feature-icon">🧠</span>
                        <h3>20 Modèles Mistral</h3>
                        <p>Accédez à tous les modèles : Code, Vision, Agent, Creative...</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🤖</span>
                        <h3>Agents Autonomes</h3>
                        <p>Créez des agents spécialisés avec sous-agents</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">📚</span>
                        <h3>Mémoire Évolutive</h3>
                        <p>SOUL, PERSONALITY, SUBCONSCIOUS, LEARNING</p>
                    </div>
                    <div class="feature-card">
                        <span class="feature-icon">🔄</span>
                        <h3>Auto-Renforcement</h3>
                        <p>Apprentissage continu et amélioration</p>
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
                        <optgroup label="Code & Développement">
                            <?php foreach ($models['code'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Flagship">
                            <?php foreach ($models['flagship'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Medium">
                            <?php foreach ($models['medium'] as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $m['id'] === 'mistral-medium-2508' ? 'selected' : '' ?>><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Small">
                            <?php foreach ($models['small'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Agent">
                            <?php foreach ($models['agent'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Vision">
                            <?php foreach ($models['vision'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Creative">
                            <?php foreach ($models['creative'] as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= $m['name'] ?> - <?= $m['desc'] ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Edge">
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
                    <textarea id="message-input" placeholder="Posez votre question ou décrivez une tâche..." rows="3"></textarea>
                    <div class="input-actions">
                        <button id="attach-btn" class="action-btn" title="Joindre un fichier/image">
                            <span>📎</span>
                        </button>
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
    
    <script src="/voanh/assets/script.js"></script>
    <script>
        const templates = {
            code: "Génère un code pour : ",
            analyze: "Analyse en détail : ",
            create: "Crée un contenu créatif sur : ",
            plan: "Planifie et décompose cette tâche : "
        };
        
        function useTemplate(type) {
            document.getElementById('message-input').value = templates[type];
            document.getElementById('message-input').focus();
        }
        
        function startNewChat() {
            document.getElementById('welcome-screen').style.display = 'block';
            document.getElementById('messages-area').style.display = 'none';
            document.getElementById('messages-list').innerHTML = '';
        }
    </script>
</body>
</html>
