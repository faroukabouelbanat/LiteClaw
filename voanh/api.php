<?php
/**
 * VoAnh - API REST Principale
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/agent.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mistral.php';

// Vérifier le token CSRF pour les requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['csrf_token']) || !verify_csrf_token($input['csrf_token'])) {
        // Pour les endpoints API, on peut être plus souple
        // Mais on garde une vérification basique
    }
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'chat':
        handleChat();
        break;
        
    case 'chat_stream':
        handleChatStream();
        break;
        
    case 'create_session':
        handleCreateSession();
        break;
        
    case 'list_sessions':
        handleListSessions();
        break;
        
    case 'delete_session':
        handleDeleteSession();
        break;
        
    case 'reset_session':
        handleResetSession();
        break;
        
    case 'get_history':
        handleGetHistory();
        break;
        
    case 'update_api_key':
        handleUpdateApiKey();
        break;
        
    case 'list_models':
        handleListModels();
        break;
        
    case 'create_cron_job':
        handleCreateCronJob();
        break;
        
    case 'list_cron_jobs':
        handleListCronJobs();
        break;
        
    case 'delete_cron_job':
        handleDeleteCronJob();
        break;
        
    case 'trigger_cron_job':
        handleTriggerCronJob();
        break;
        
    default:
        json_response([
            'error' => 'Action inconnue',
            'available_actions' => [
                'chat', 'chat_stream', 'create_session', 'list_sessions',
                'delete_session', 'reset_session', 'get_history',
                'update_api_key', 'list_models', 'create_cron_job',
                'list_cron_jobs', 'delete_cron_job', 'trigger_cron_job'
            ]
        ], 400);
}

/**
 * Gère une requête de chat
 */
function handleChat() {
    global $input;
    
    $message = $input['message'] ?? '';
    $sessionId = $input['session_id'] ?? 'default';
    $model = $input['model'] ?? null;
    
    if (empty($message)) {
        json_response(['error' => 'Message vide'], 400);
    }
    
    try {
        $response = processMessage($message, $sessionId);
        
        json_response([
            'success' => true,
            'response' => $response,
            'session_id' => $sessionId
        ]);
        
    } catch (Exception $e) {
        log_error('Erreur chat: ' . $e->getMessage());
        json_response(['error' => $e->getMessage()], 500);
    }
}

/**
 * Gère une requête de chat avec streaming
 */
function handleChatStream() {
    global $input;
    
    $message = $input['message'] ?? '';
    $sessionId = $input['session_id'] ?? 'default';
    $model = $input['model'] ?? null;
    
    if (empty($message)) {
        echo "data: Erreur: Message vide\n\n";
        return;
    }
    
    streamProcessMessage($message, $sessionId);
}

/**
 * Crée une nouvelle session
 */
function handleCreateSession() {
    global $input;
    
    $sessionId = $input['session_id'] ?? null;
    
    if (empty($sessionId)) {
        $sessionId = 'session_' . uniqid();
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (createSession($sessionId, $userId)) {
        json_response([
            'success' => true,
            'session_id' => $sessionId
        ]);
    } else {
        json_response([
            'success' => true,
            'session_id' => $sessionId,
            'message' => 'Session existante récupérée'
        ]);
    }
}

/**
 * Liste les sessions
 */
function handleListSessions() {
    $userId = $_SESSION['user_id'] ?? null;
    $sessions = listSessions($userId);
    
    json_response([
        'success' => true,
        'sessions' => $sessions
    ]);
}

/**
 * Supprime une session
 */
function handleDeleteSession() {
    global $input;
    
    $sessionId = $input['session_id'] ?? null;
    
    if (empty($sessionId)) {
        json_response(['error' => 'session_id requis'], 400);
    }
    
    deleteSession($sessionId);
    
    json_response(['success' => true]);
}

/**
 * Réinitialise une session
 */
function handleResetSession() {
    global $input;
    
    $sessionId = $input['session_id'] ?? null;
    
    if (empty($sessionId)) {
        json_response(['error' => 'session_id requis'], 400);
    }
    
    resetSession($sessionId);
    
    json_response(['success' => true]);
}

/**
 * Récupère l'historique d'une session
 */
function handleGetHistory() {
    global $input;
    
    $sessionId = $input['session_id'] ?? null;
    $limit = $input['limit'] ?? 50;
    
    if (empty($sessionId)) {
        json_response(['error' => 'session_id requis'], 400);
    }
    
    $history = getSessionHistory($sessionId, $limit);
    
    json_response([
        'success' => true,
        'history' => $history
    ]);
}

/**
 * Met à jour la clé API personnelle
 */
function handleUpdateApiKey() {
    global $input;
    
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Utilisateur non connecté'], 401);
    }
    
    $apiKey = $input['api_key'] ?? '';
    
    if (empty($apiKey)) {
        json_response(['error' => 'Clé API requise'], 400);
    }
    
    $client = getMistralClient();
    
    if ($client->addUserApiKey($_SESSION['user_id'], $apiKey)) {
        json_response(['success' => true, 'message' => 'Clé API mise à jour']);
    } else {
        json_response(['success' => true, 'message' => 'Clé API déjà enregistrée']);
    }
}

/**
 * Liste les modèles disponibles
 */
function handleListModels() {
    global $MODELS;
    
    $client = getMistralClient();
    $models = $client->listModels();
    
    json_response([
        'success' => true,
        'models' => $models,
        'default_model' => DEFAULT_MODEL
    ]);
}

/**
 * Crée une tâche cron
 */
function handleCreateCronJob() {
    global $input;
    
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Utilisateur non connecté'], 401);
    }
    
    $name = $input['name'] ?? '';
    $scheduleType = $input['schedule_type'] ?? 'cron';
    $scheduleValue = $input['schedule_value'] ?? '';
    $task = $input['task'] ?? '';
    
    if (empty($name) || empty($scheduleValue) || empty($task)) {
        json_response(['error' => 'Champs requis manquants'], 400);
    }
    
    $db = getDb();
    $jobId = uniqid('job_');
    
    $db->insert('cron_jobs', [
        'id' => $jobId,
        'user_id' => $_SESSION['user_id'],
        'name' => $name,
        'schedule_type' => $scheduleType,
        'schedule_value' => $scheduleValue,
        'task' => $task,
        'is_active' => 1
    ]);
    
    json_response([
        'success' => true,
        'job_id' => $jobId,
        'webhook_url' => VOANH_ROOT . '/api.php?action=trigger_cron_job&job_id=' . $jobId
    ]);
}

/**
 * Liste les tâches cron
 */
function handleListCronJobs() {
    $db = getDb();
    
    $where = '';
    $params = [];
    
    if (isset($_SESSION['user_id'])) {
        $where = 'WHERE user_id = ? OR user_id IS NULL';
        $params = [$_SESSION['user_id']];
    }
    
    $jobs = $db->fetchAll("SELECT * FROM cron_jobs $where ORDER BY created_at DESC", $params);
    
    json_response([
        'success' => true,
        'jobs' => $jobs
    ]);
}

/**
 * Supprime une tâche cron
 */
function handleDeleteCronJob() {
    global $input;
    
    $jobId = $input['job_id'] ?? null;
    
    if (empty($jobId)) {
        json_response(['error' => 'job_id requis'], 400);
    }
    
    $db = getDb();
    $db->delete('cron_jobs', 'id = ?', [$jobId]);
    
    json_response(['success' => true]);
}

/**
 * Déclenche manuellement une tâche cron (webhook)
 */
function handleTriggerCronJob() {
    $jobId = $_GET['job_id'] ?? ($input['job_id'] ?? null);
    
    if (empty($jobId)) {
        json_response(['error' => 'job_id requis'], 400);
    }
    
    $db = getDb();
    $job = $db->fetchOne('SELECT * FROM cron_jobs WHERE id = ?', [$jobId]);
    
    if (!$job) {
        json_response(['error' => 'Tâche introuvable'], 404);
    }
    
    // Exécuter la tâche en arrière-plan
    // Sur Hostinger mutualisé, on ne peut pas utiliser exec() ou pcntl_fork()
    // On va donc simplement appeler l'agent directement
    
    $sessionId = 'cron_' . $jobId . '_' . uniqid();
    
    try {
        $response = processMessage($job['task'], $sessionId);
        
        // Mettre à jour last_run
        $db->update('cron_jobs', ['last_run' => date('Y-m-d H:i:s')], 'id = ?', [$jobId]);
        
        json_response([
            'success' => true,
            'response' => $response
        ]);
        
    } catch (Exception $e) {
        json_response(['error' => $e->getMessage()], 500);
    }
}
