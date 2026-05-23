<?php
/**
 * VoAnh - API de Chat avec Mistral AI
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/auth.php';
require_once CORE_PATH . '/mistral.php';
require_once CORE_PATH . '/agent.php';
require_once CORE_PATH . '/memory.php';

header('Content-Type: application/json');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty(trim($input['message']))) {
    echo json_encode(['success' => false, 'error' => 'Message requis']);
    exit;
}

$message = trim($input['message']);
$model = $input['model'] ?? MASTER_AGENT_MODEL;
$conversationId = $input['conversation_id'] ?? null;

$auth = new Auth();
$user = $auth->getCurrentUser();
$userId = $user ? $user['id'] : null;
$userApiKey = $user ? $user['mistral_api_key'] : null;

$db = Database::getInstance();
$mistral = getMistralClient($userApiKey);
$memory = new MemorySystem();

try {
    // Créer ou récupérer la conversation
    if (!$conversationId && $userId) {
        $conversationId = $db->insert('conversations', [
            ':user_id' => $userId,
            ':title' => substr($message, 0, 50),
            ':model_used' => $model
        ]);
    } elseif ($conversationId) {
        $db->update('conversations', [
            ':updated_at' => date('Y-m-d H:i:s'),
            ':model_used' => $model
        ], 'id = :id', [':id' => $conversationId]);
    }
    
    // Sauvegarder le message utilisateur
    if ($conversationId) {
        $db->insert('messages', [
            ':conversation_id' => $conversationId,
            ':role' => 'user',
            ':content' => $message,
            ':model_used' => $model
        ]);
    }
    
    // Construire l'historique de conversation
    $messages = [];
    
    // Ajouter le contexte de mémoire si utilisateur connecté
    if ($userId && AUTO_LEARNING_ENABLED) {
        $context = $memory->buildContextForAI($userId, 'general');
        if (!empty($context['user_identity']) || !empty($context['core_values'])) {
            $systemContext = "Contexte utilisateur:\n";
            if (!empty($context['user_identity'])) {
                $systemContext .= "- Identité: " . json_encode($context['user_identity']) . "\n";
            }
            if (!empty($context['core_values'])) {
                $systemContext .= "- Valeurs: " . implode(', ', $context['core_values']) . "\n";
            }
            $messages[] = ['role' => 'system', 'content' => $systemContext];
        }
    }
    
    // Récupérer les derniers messages de la conversation
    if ($conversationId) {
        $history = $db->fetchAll(
            "SELECT role, content FROM messages WHERE conversation_id = :id ORDER BY created_at DESC LIMIT 10",
            [':id' => $conversationId]
        );
        
        foreach (array_reverse($history) as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }
    }
    
    // Appel à l'API Mistral
    $result = $mistral->chat($messages, $model, [
        'temperature' => 0.7,
        'max_tokens' => 4096
    ]);
    
    if ($result['success']) {
        $assistantMessage = $result['content'];
        
        // Sauvegarder la réponse de l'assistant
        if ($conversationId) {
            $db->insert('messages', [
                ':conversation_id' => $conversationId,
                ':role' => 'assistant',
                ':content' => $assistantMessage,
                ':model_used' => $model,
                ':tokens_used' => $result['usage']['total_tokens'] ?? 0
            ]);
        }
        
        // Auto-renforcement: analyser et apprendre de l'interaction
        if ($userId && AUTO_LEARNING_ENABLED) {
            // Mettre à jour la mémoire subconscious avec les patterns détectés
            $memory->updateSubconsciousMemory($userId, [
                'type' => 'interaction_pattern',
                'value' => substr($message, 0, 100)
            ], 0.5);
        }
        
        echo json_encode([
            'success' => true,
            'content' => $assistantMessage,
            'model' => $model,
            'conversation_id' => $conversationId,
            'usage' => $result['usage'] ?? []
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Erreur inconnue'
        ]);
    }
    
} catch (Exception $e) {
    voanh_log("Chat API error: " . $e->getMessage(), 1);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur interne du serveur'
    ]);
}
