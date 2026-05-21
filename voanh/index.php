<?php
/**
 * VoAnh - Page Principale (Interface de Chat)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mistral.php';

// Rediriger vers login si pas connecté (optionnel, on peut permettre l'accès sans compte)
// requireLogin();

$currentUser = getCurrentUser();
$userPreferences = null;
if ($currentUser) {
    $userPreferences = getUserPreferences($currentUser['id']);
}

// Récupérer les modèles disponibles
global $MODELS;
$client = getMistralClient();
$availableModels = $client->listModels();

// Générer le token CSRF
$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VoAnh - Intelligence Artificielle</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="chat-page">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1 class="logo">🦞 VoAnh</h1>
            <span class="subtitle">Assistant IA Puissant</span>
        </div>
        
        <nav class="header-nav">
            <a href="index.php" class="nav-link active">Chat</a>
            <?php if ($currentUser): ?>
                <a href="dashboard.php" class="nav-link">Tableau de bord</a>
                <a href="history.php" class="nav-link">Historique</a>
                <a href="settings.php" class="nav-link">Paramètres</a>
                <a href="logout.php" class="nav-link btn-logout">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
                <a href="register.php" class="nav-link btn-register">S'inscrire</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Container Principal -->
    <div class="main-container">
        <!-- Sidebar - Sessions -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>Sessions</h2>
                <button id="newSessionBtn" class="btn btn-sm btn-primary">+ Nouvelle</button>
            </div>
            
            <div id="sessionsList" class="sessions-list">
                <!-- Les sessions seront chargées ici par JS -->
                <div class="loading">Chargement...</div>
            </div>
            
            <div class="sidebar-footer">
                <?php if ($currentUser): ?>
                    <div class="user-info">
                        <span class="user-email"><?php echo htmlspecialchars($currentUser['email']); ?></span>
                    </div>
                <?php else: ?>
                    <div class="guest-info">
                        <span>Mode invité</span>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Zone de Chat -->
        <main class="chat-area">
            <!-- Sélection du modèle -->
            <div class="model-selector-container">
                <label for="modelSelect">Modèle:</label>
                <select id="modelSelect" class="model-select">
                    <?php foreach ($availableModels as $modelId => $modelInfo): ?>
                        <option value="<?php echo htmlspecialchars($modelId); ?>" 
                                data-category="<?php echo htmlspecialchars($modelInfo['category']); ?>"
                                <?php echo ($modelId === DEFAULT_MODEL) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($modelId); ?> - <?php echo htmlspecialchars($modelInfo['description']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span id="modelDescription" class="model-description"></span>
            </div>

            <!-- Messages -->
            <div id="messagesContainer" class="messages-container">
                <div class="welcome-message">
                    <h2>Bienvenue sur VoAnh 🦞</h2>
                    <p>Je suis votre assistant IA powered by Mistral AI.</p>
                    <p>Sélectionnez un modèle et commencez à discuter !</p>
                    
                    <div class="quick-actions">
                        <button class="quick-action-btn" data-prompt="Explique-moi ce qu'est une AGI">
                            💡 Qu'est-ce qu'une AGI ?
                        </button>
                        <button class="quick-action-btn" data-prompt="Aide-moi à écrire du code Python">
                            💻 Aide au codage
                        </button>
                        <button class="quick-action-btn" data-prompt="Raconte-moi une histoire créative">
                            📖 Histoire créative
                        </button>
                        <button class="quick-action-btn" data-prompt="Analyse ce sujet de manière approfondie">
                            🧠 Analyse approfondie
                        </button>
                    </div>
                </div>
            </div>

            <!-- Zone de saisie -->
            <div class="input-area">
                <form id="chatForm" class="chat-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="session_id" id="sessionId" value="">
                    
                    <textarea 
                        id="messageInput" 
                        name="message" 
                        placeholder="Écrivez votre message ici..." 
                        rows="3"
                        required
                    ></textarea>
                    
                    <div class="input-actions">
                        <button type="submit" id="sendBtn" class="btn btn-primary">
                            Envoyer 🚀
                        </button>
                        <button type="button" id="stopBtn" class="btn btn-danger" style="display:none;">
                            Arrêter ⏹️
                        </button>
                        <button type="button" id="clearBtn" class="btn btn-secondary">
                            Effacer 🗑️
                        </button>
                    </div>
                </form>
                
                <div class="status-bar">
                    <span id="connectionStatus" class="status-indicator connected">● Connecté</span>
                    <span id="tokenUsage" class="token-usage"></span>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal pour confirmation -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Confirmation</h3>
            <p id="modalMessage">Êtes-vous sûr ?</p>
            <div class="modal-actions">
                <button id="modalConfirm" class="btn btn-primary">Confirmer</button>
                <button id="modalCancel" class="btn btn-secondary">Annuler</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Token CSRF
            window.csrfToken = '<?php echo $csrfToken; ?>';
            
            // Session ID par défaut
            const defaultSessionId = 'session_' + Date.now();
            document.getElementById('sessionId').value = defaultSessionId;
            
            // Charger les sessions
            loadSessions();
            
            // Gestion des actions rapides
            document.querySelectorAll('.quick-action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const prompt = this.getAttribute('data-prompt');
                    document.getElementById('messageInput').value = prompt;
                    document.getElementById('chatForm').dispatchEvent(new Event('submit'));
                });
            });
            
            // Changement de modèle - mettre à jour la description
            document.getElementById('modelSelect').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const description = selectedOption.text.split(' - ')[1] || '';
                document.getElementById('modelDescription').textContent = description;
            });
            
            // Auto-resize du textarea
            const textarea = document.getElementById('messageInput');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Focus sur le champ de message
            textarea.focus();
        });
    </script>
</body>
</html>
