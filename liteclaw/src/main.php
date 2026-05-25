<?php
/**
 * LiteClaw Main API - PHP Version
 * API principale avec interface web intégrée
 */

require_once __DIR__ . '/agent.php';
require_once __DIR__ . '/memory.php';

// Configuration de base
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Router simple
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/liteclaw/src/#', '', $path);

switch ($path) {
    case 'chat':
    case 'api/chat':
        handleChat($method);
        break;
    
    case 'session/create':
    case 'api/session/create':
        handleCreateSession($method);
        break;
    
    case 'sessions/list':
    case 'api/sessions/list':
        handleListSessions($method);
        break;
    
    case 'reset':
    case 'api/reset':
        handleReset($method);
        break;
    
    default:
        // Interface web par défaut
        if ($method === 'GET') {
            serveWebInterface();
        } else {
            jsonResponse(['error' => 'Not Found'], 404);
        }
        break;
}

function handleChat(string $method): void {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $message = $input['message'] ?? '';
    $sessionId = $input['session_id'] ?? 'default';
    $stream = $input['stream'] ?? false;
    
    if (empty($message)) {
        jsonResponse(['error' => 'Message is required'], 400);
        return;
    }
    
    try {
        if ($stream) {
            // Streaming non supporté en PHP sans extensions spéciales
            // On retourne une réponse normale avec un avertissement
            $response = processMessage($message, $sessionId, 'api');
            jsonResponse([
                'response' => $response,
                'warning' => 'Streaming not supported in PHP version'
            ]);
        } else {
            $response = processMessage($message, $sessionId, 'api');
            jsonResponse(['response' => $response]);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

function handleCreateSession(string $method): void {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;
    
    if (!$sessionId) {
        $sessionId = bin2hex(random_bytes(8));
    }
    
    if (strpos($sessionId, ' ') !== false) {
        jsonResponse(['error' => 'Session ID cannot contain spaces'], 400);
        return;
    }
    
    if (createSession($sessionId)) {
        jsonResponse(['session_id' => $sessionId, 'status' => 'created']);
    } else {
        jsonResponse(['session_id' => $sessionId, 'status' => 'exists']);
    }
}

function handleListSessions(string $method): void {
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $sessions = listSessions();
    jsonResponse(['sessions' => $sessions]);
}

function handleReset(string $method): void {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? 'default';
    
    if (resetSession($sessionId)) {
        jsonResponse(['status' => 'reset', 'message' => 'Session cleared']);
    } else {
        jsonResponse(['error' => 'Failed to reset session'], 500);
    }
}

function serveWebInterface(): void {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiteClaw - AGI Assistant</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; display: flex; flex-direction: column; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; width: 100%; flex: 1; display: flex; flex-direction: column; }
        h1 { text-align: center; margin-bottom: 20px; color: #00d9ff; }
        .chat-container { flex: 1; display: flex; flex-direction: column; background: #16213e; border-radius: 12px; overflow: hidden; }
        .messages { flex: 1; overflow-y: auto; padding: 20px; min-height: 400px; }
        .message { margin-bottom: 15px; padding: 12px 16px; border-radius: 8px; max-width: 80%; }
        .user { background: #0f3460; margin-left: auto; text-align: right; }
        .assistant { background: #1a1a2e; }
        .system { background: #e94560; color: white; text-align: center; max-width: 100%; }
        .input-area { display: flex; padding: 15px; background: #0f3460; gap: 10px; }
        input[type="text"] { flex: 1; padding: 12px; border: none; border-radius: 6px; background: #1a1a2e; color: #fff; font-size: 16px; }
        input[type="text"]:focus { outline: 2px solid #00d9ff; }
        button { padding: 12px 24px; background: #00d9ff; color: #1a1a2e; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.2s; }
        button:hover { background: #00b8d9; }
        .controls { display: flex; gap: 10px; margin-bottom: 15px; }
        .controls button { padding: 8px 16px; font-size: 14px; background: #1a1a2e; color: #00d9ff; border: 1px solid #00d9ff; }
        .controls button:hover { background: #00d9ff; color: #1a1a2e; }
        .loading { opacity: 0.6; pointer-events: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 LiteClaw - AGI Assistant</h1>
        
        <div class="controls">
            <button onclick="createNewSession()">New Session</button>
            <button onclick="resetSession()">Reset Current</button>
            <span id="sessionId" style="margin-left: auto; align-self: center; color: #888;">Session: default</span>
        </div>
        
        <div class="chat-container">
            <div class="messages" id="messages"></div>
            <div class="input-area">
                <input type="text" id="messageInput" placeholder="Type your message..." onkeypress="if(event.key==='Enter') sendMessage()">
                <button onclick="sendMessage()" id="sendBtn">Send</button>
            </div>
        </div>
    </div>

    <script>
        let currentSession = 'default';
        
        function addMessage(content, role) {
            const div = document.createElement('div');
            div.className = `message ${role}`;
            div.textContent = content;
            document.getElementById('messages').appendChild(div);
            document.getElementById('messages').scrollTop = document.getElementById('messages').scrollHeight;
        }
        
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if (!message) return;
            
            addMessage(message, 'user');
            input.value = '';
            
            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.classList.add('loading');
            
            try {
                const response = await fetch('chat', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        message: message,
                        session_id: currentSession,
                        stream: false
                    })
                });
                
                const data = await response.json();
                
                if (data.error) {
                    addMessage('Error: ' + data.error, 'system');
                } else {
                    addMessage(data.response || 'No response', 'assistant');
                }
            } catch (err) {
                addMessage('Network error: ' + err.message, 'system');
            } finally {
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        }
        
        async function createNewSession() {
            try {
                const response = await fetch('session/create', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({})
                });
                
                const data = await response.json();
                currentSession = data.session_id;
                document.getElementById('sessionId').textContent = 'Session: ' + currentSession;
                document.getElementById('messages').innerHTML = '';
                addMessage('New session created: ' + currentSession, 'system');
            } catch (err) {
                addMessage('Error creating session: ' + err.message, 'system');
            }
        }
        
        async function resetSession() {
            if (!confirm('Reset current session?')) return;
            
            try {
                await fetch('reset', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({session_id: currentSession})
                });
                
                document.getElementById('messages').innerHTML = '';
                addMessage('Session reset successfully', 'system');
            } catch (err) {
                addMessage('Error resetting session: ' + err.message, 'system');
            }
        }
        
        // Message de bienvenue
        addMessage('Welcome to LiteClaw! I am your AGI assistant. How can I help you today?', 'assistant');
    </script>
</body>
</html>
    <?php
    exit();
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}
