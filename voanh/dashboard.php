<?php
/**
 * VoAnh - Tableau de Bord Utilisateur
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Nécessite une connexion
requireLogin();

$currentUser = getCurrentUser();
$userPreferences = getUserPreferences($currentUser['id']);

// Récupérer les statistiques
$db = getDb();

// Nombre de sessions
$sessionsCount = $db->fetchOne(
    'SELECT COUNT(*) as count FROM sessions WHERE user_id = ?',
    [$currentUser['id']]
)['count'] ?? 0;

// Nombre de messages
$messagesCount = $db->fetchOne('
    SELECT COUNT(*) as count 
    FROM messages m
    JOIN sessions s ON m.session_id = s.session_id
    WHERE s.user_id = ?
', [$currentUser['id']])['count'] ?? 0;

// Dernières sessions
$recentSessions = $db->fetchAll(
    'SELECT session_id, created_at FROM sessions 
     WHERE user_id = ? 
     ORDER BY created_at DESC LIMIT 5',
    [$currentUser['id']]
);

// Tâches cron
$cronJobsCount = $db->fetchOne(
    'SELECT COUNT(*) as count FROM cron_jobs WHERE user_id = ?',
    [$currentUser['id']]
)['count'] ?? 0;

$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - VoAnh</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dashboard-page">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1 class="logo">🦞 VoAnh</h1>
            <span class="subtitle">Tableau de bord</span>
        </div>
        
        <nav class="header-nav">
            <a href="index.php" class="nav-link">Chat</a>
            <a href="dashboard.php" class="nav-link active">Tableau de bord</a>
            <a href="history.php" class="nav-link">Historique</a>
            <a href="settings.php" class="nav-link">Paramètres</a>
            <a href="logout.php" class="nav-link btn-logout">Déconnexion</a>
        </nav>
    </header>

    <!-- Contenu Principal -->
    <main class="dashboard-content">
        <div class="dashboard-container">
            <!-- Carte de profil -->
            <div class="card profile-card">
                <h2>👤 Profil</h2>
                <div class="profile-info">
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($currentUser['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Membre depuis:</span>
                        <span class="value"><?php echo date('d/m/Y', strtotime($currentUser['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Clé API personnelle:</span>
                        <span class="value"><?php echo $currentUser['mistral_api_key'] ? '✅ Configurée' : '❌ Non configurée'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Modèle par défaut:</span>
                        <span class="value"><?php echo htmlspecialchars($userPreferences['default_model'] ?? DEFAULT_MODEL); ?></span>
                    </div>
                </div>
                <a href="settings.php" class="btn btn-primary">Modifier le profil</a>
            </div>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="card stat-card">
                    <div class="stat-icon">💬</div>
                    <div class="stat-value"><?php echo $sessionsCount; ?></div>
                    <div class="stat-label">Sessions</div>
                </div>
                
                <div class="card stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-value"><?php echo $messagesCount; ?></div>
                    <div class="stat-label">Messages</div>
                </div>
                
                <div class="card stat-card">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-value"><?php echo $cronJobsCount; ?></div>
                    <div class="stat-label">Tâches Cron</div>
                </div>
                
                <div class="card stat-card">
                    <div class="stat-icon">🤖</div>
                    <div class="stat-value">20</div>
                    <div class="stat-label">Modèles</div>
                </div>
            </div>

            <!-- Sessions récentes -->
            <div class="card">
                <h2>📋 Sessions Récentes</h2>
                <?php if (empty($recentSessions)): ?>
                    <p class="no-data">Aucune session pour le moment</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Session ID</th>
                                <th>Date de création</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSessions as $session): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($session['session_id']); ?></code></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($session['created_at'])); ?></td>
                                    <td>
                                        <a href="index.php?session=<?php echo urlencode($session['session_id']); ?>" 
                                           class="btn btn-sm btn-primary">Ouvrir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Informations sur l'API -->
            <div class="card">
                <h2>🔑 Informations API Mistral</h2>
                <div class="api-info">
                    <p><strong>Endpoint:</strong> <code><?php echo MISTRAL_ENDPOINT; ?></code></p>
                    <p><strong>Timeout:</strong> <?php echo MISTRAL_TIMEOUT; ?> secondes</p>
                    <p><strong>Clés par défaut:</strong> <?php echo count(DEFAULT_MISTRAL_API_KEYS); ?> clés configurées</p>
                    <p><strong>Limite de tokens:</strong> 1 milliard de tokens/mois par clé</p>
                    
                    <div class="models-overview">
                        <h3>Modèles disponibles par catégorie:</h3>
                        <ul>
                            <li><strong>Code:</strong> codestral-2508, devstral-2512, devstral-medium-2507, devstral-small-2507</li>
                            <li><strong>Flagship:</strong> mistral-large-2512, mistral-large-2411</li>
                            <li><medium>Medium:</strong> mistral-medium-2508, mistral-medium-2505</li>
                            <li><strong>Small:</strong> mistral-small-2603, mistral-small-2506</li>
                            <li><strong>Agent:</strong> magistral-medium-2509, magistral-small-2509</li>
                            <li><strong>Creative:</strong> labs-mistral-small-creative</li>
                            <li><strong>Vision:</strong> pixtral-large-2411, pixtral-12b-2409</li>
                            <li><strong>Edge:</strong> ministral-14b-2512, ministral-8b-2512, ministral-3b-2512</li>
                            <li><strong>Audio:</strong> voxtral-small-2507, voxtral-mini-2507</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .dashboard-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .dashboard-container {
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
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .profile-card .profile-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-row .label {
            color: var(--text-secondary);
        }
        
        .info-row .value {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .data-table code {
            background: var(--bg-input);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
        }
        
        .no-data {
            color: var(--text-secondary);
            text-align: center;
            padding: 2rem;
        }
        
        .api-info {
            line-height: 1.8;
        }
        
        .api-info code {
            background: var(--bg-input);
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-family: monospace;
        }
        
        .models-overview {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .models-overview ul {
            list-style: none;
            padding-left: 0;
        }
        
        .models-overview li {
            padding: 0.5rem 0;
            color: var(--text-secondary);
        }
        
        .models-overview strong {
            color: var(--text-primary);
        }
    </style>
</body>
</html>
