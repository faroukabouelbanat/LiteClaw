<?php
/**
 * LiteClaw Main - PHP Version
 * API principale et point d'entrée de l'application
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/agent.php';
require_once __DIR__ . '/memory.php';

class LiteClawAPI {
    private array $sessions = [];
    
    /**
     * Créer une nouvelle session
     */
    public function createSession(?string $sessionId = null): string {
        if ($sessionId === null) {
            $sessionId = 'session_' . bin2hex(random_bytes(8));
        }
        
        createSession($sessionId);
        $this->sessions[$sessionId] = createAgent($sessionId);
        
        return $sessionId;
    }
    
    /**
     * Envoyer un message à l'agent
     */
    public function chat(string $message, string $sessionId = 'default', bool $stream = false): array {
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = createAgent($sessionId);
        }
        
        $agent = $this->sessions[$sessionId];
        return $agent->chat($message);
    }
    
    /**
     * Réinitialiser une session
     */
    public function resetSession(string $sessionId): bool {
        if (isset($this->sessions[$sessionId])) {
            $result = $this->sessions[$sessionId]->reset();
            unset($this->sessions[$sessionId]);
            return $result;
        }
        return resetSession($sessionId);
    }
    
    /**
     * Lister les sessions
     */
    public function listSessions(): array {
        return listSessions();
    }
    
    /**
     * Obtenir l'historique d'une session
     */
    public function getSessionHistory(string $sessionId, int $limit = 20): array {
        return getSessionHistory($sessionId, $limit);
    }
}

// Initialisation de l'API
$api = new LiteClawAPI();

// Gestion des requêtes HTTP simples
if ($_SERVER['REQUEST_METHOD'] ?? '' === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_session':
                $sessionId = $api->createSession($input['session_id'] ?? null);
                echo json_encode(['session_id' => $sessionId]);
                break;
                
            case 'chat':
                $response = $api->chat(
                    $input['message'],
                    $input['session_id'] ?? 'default',
                    $input['stream'] ?? false
                );
                echo json_encode($response);
                break;
                
            case 'reset_session':
                $success = $api->resetSession($input['session_id']);
                echo json_encode(['success' => $success]);
                break;
                
            case 'list_sessions':
                $sessions = $api->listSessions();
                echo json_encode(['sessions' => $sessions]);
                break;
                
            case 'get_history':
                $history = $api->getSessionHistory(
                    $input['session_id'],
                    $input['limit'] ?? 20
                );
                echo json_encode(['history' => $history]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Interface HTML simple pour tester
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiteClaw - Interface de Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .chat-box { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .message { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .user { background-color: #e3f2fd; }
        .assistant { background-color: #f5f5f5; }
        input[type="text"] { width: 70%; padding: 10px; }
        button { padding: 10px 20px; background-color: #2196F3; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #1976D2; }
    </style>
</head>
<body>
    <h1>🦞 LiteClaw - Interface de Test</h1>
    
    <div class="chat-box" id="chat">
        <div class="message assistant">Bonjour! Je suis LiteClaw. Comment puis-je vous aider?</div>
    </div>
    
    <form id="chat-form">
        <input type="text" id="message" placeholder="Votre message..." required>
        <button type="submit">Envoyer</button>
    </form>
    
    <script>
        const form = document.getElementById('chat-form');
        const chatBox = document.getElementById('chat');
        const messageInput = document.getElementById('message');
        let sessionId = localStorage.getItem('liteclaw_session_id') || 'default';
        
        // Créer une session au chargement
        fetch('?action=create_session')
            .then(r => r.json())
            .then(data => {
                sessionId = data.session_id;
                localStorage.setItem('liteclaw_session_id', sessionId);
            });
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Afficher le message utilisateur
            chatBox.innerHTML += `<div class="message user"><strong>Vous:</strong> ${message}</div>`;
            messageInput.value = '';
            
            // Envoyer à l'API
            try {
                const response = await fetch('?action=chat', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({message, session_id: sessionId})
                });
                const data = await response.json();
                
                if (data.error) {
                    chatBox.innerHTML += `<div class="message assistant"><strong>Erreur:</strong> ${data.error}</div>`;
                } else if (data.message) {
                    const content = data.message.content || '...';
                    chatBox.innerHTML += `<div class="message assistant"><strong>LiteClaw:</strong> ${content}</div>`;
                }
                
                chatBox.scrollTop = chatBox.scrollHeight;
            } catch (err) {
                chatBox.innerHTML += `<div class="message assistant"><strong>Erreur:</strong> ${err.message}</div>`;
            }
        });
    </script>
</body>
</html>
