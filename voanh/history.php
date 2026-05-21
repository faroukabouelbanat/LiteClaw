<?php
/**
 * VoAnh - Page d'Historique des Conversations
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Nécessite une connexion (optionnel, on peut afficher les sessions publiques)
// requireLogin();

$currentUser = getCurrentUser();
$db = getDb();

// Récupérer toutes les sessions (filtrées par utilisateur si connecté)
if ($currentUser) {
    $sessions = $db->fetchAll('
        SELECT s.session_id, s.created_at, 
               COUNT(m.id) as message_count,
               MAX(m.timestamp) as last_message_at
        FROM sessions s
        LEFT JOIN messages m ON s.session_id = m.session_id
        WHERE s.user_id = ? OR s.user_id IS NULL
        GROUP BY s.session_id
        ORDER BY last_message_at DESC
    ', [$currentUser['id']]);
} else {
    $sessions = $db->fetchAll('
        SELECT s.session_id, s.created_at, 
               COUNT(m.id) as message_count,
               MAX(m.timestamp) as last_message_at
        FROM sessions s
        LEFT JOIN messages m ON s.session_id = m.session_id
        WHERE s.user_id IS NULL
        GROUP BY s.session_id
        ORDER BY last_message_at DESC
        LIMIT 50
    ');
}

$csrfToken = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - VoAnh</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="history-page">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1 class="logo">🦞 VoAnh</h1>
            <span class="subtitle">Historique des conversations</span>
        </div>
        
        <nav class="header-nav">
            <a href="index.php" class="nav-link">Chat</a>
            <?php if ($currentUser): ?>
                <a href="dashboard.php" class="nav-link">Tableau de bord</a>
                <a href="history.php" class="nav-link active">Historique</a>
                <a href="settings.php" class="nav-link">Paramètres</a>
                <a href="logout.php" class="nav-link btn-logout">Déconnexion</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Connexion</a>
                <a href="register.php" class="nav-link btn-register">S'inscrire</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Contenu Principal -->
    <main class="history-content">
        <div class="history-container">
            <div class="card">
                <h2>📜 Historique des Sessions</h2>
                
                <?php if (empty($sessions)): ?>
                    <p class="no-data">Aucune session trouvée</p>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Session ID</th>
                                <th>Date de création</th>
                                <th>Dernier message</th>
                                <th>Messages</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($session['session_id']); ?></code>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($session['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($session['last_message_at']): ?>
                                            <?php echo date('d/m/Y H:i', strtotime($session['last_message_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge"><?php echo $session['message_count']; ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="index.php?session=<?php echo urlencode($session['session_id']); ?>" 
                                               class="btn btn-sm btn-primary"
                                               title="Ouvrir la session">
                                                📖 Ouvrir
                                            </a>
                                            <button onclick="deleteSession('<?php echo htmlspecialchars($session['session_id']); ?>')"
                                                    class="btn btn-sm btn-danger"
                                                    title="Supprimer la session">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Fonction pour supprimer une session
        async function deleteSession(sessionId) {
            if (!confirm('Voulez-vous vraiment supprimer cette session ?')) {
                return;
            }
            
            try {
                const response = await fetch('api.php?action=delete_session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        csrf_token: '<?php echo $csrfToken; ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Recharger la page
                    window.location.reload();
                } else {
                    alert('Erreur: ' + (data.error || 'Échec de la suppression'));
                }
            } catch (error) {
                alert('Erreur de connexion: ' + error.message);
            }
        }
    </script>

    <style>
        .history-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .history-container {
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
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th,
        .history-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .history-table th {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .history-table code {
            background: var(--bg-input);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: monospace;
            font-size: 0.85rem;
            word-break: break-all;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--primary-color);
            color: white;
            border-radius: 1rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .text-muted {
            color: var(--text-secondary);
        }
        
        .no-data {
            text-align: center;
            color: var(--text-secondary);
            padding: 3rem;
        }
        
        @media (max-width: 768px) {
            .history-table {
                font-size: 0.85rem;
            }
            
            .history-table th,
            .history-table td {
                padding: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
