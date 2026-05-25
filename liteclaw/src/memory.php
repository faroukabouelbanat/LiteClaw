<?php
/**
 * LiteClaw Memory - PHP Version
 * Gestion des sessions et messages
 */

require_once __DIR__ . '/db.php';

function createSession(string $sessionId, ?string $parentSessionId = null): bool {
    $conn = getDbConnection();
    try {
        $stmt = $conn->prepare("INSERT INTO sessions (session_id, parent_session_id) VALUES (?, ?)");
        $stmt->execute([$sessionId, $parentSessionId]);
        return true;
    } catch (Exception $e) {
        // Session existe déjà probablement
        return false;
    }
}

function listSessions(): array {
    $conn = getDbConnection();
    try {
        $stmt = $conn->query("SELECT session_id, created_at FROM sessions ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function addMessage(string $sessionId, array $message): void {
    $conn = getDbConnection();
    
    $role = $message['role'] ?? '';
    $content = $message['content'] ?? '';
    $toolCallId = $message['tool_call_id'] ?? null;
    $name = $message['name'] ?? null;
    
    // Vérifier le dernier message pour éviter les doublons
    $stmt = $conn->prepare("
        SELECT role, content, tool_call_id, name FROM messages 
        WHERE session_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$sessionId]);
    $last = $stmt->fetch();
    
    if ($last) {
        if ($last['role'] === $role && 
            $last['content'] === $content && 
            $last['tool_call_id'] === $toolCallId && 
            $last['name'] === $name) {
            return;
        }
    }
    
    // Sérialiser les tool_calls si présents
    $toolCalls = null;
    if (!empty($message['tool_calls'])) {
        $serialized = [];
        foreach ($message['tool_calls'] as $tc) {
            $serialized[] = [
                'id' => is_object($tc) ? ($tc->id ?? $tc['id'] ?? '') : ($tc['id'] ?? ''),
                'type' => is_object($tc) ? ($tc->type ?? $tc['type'] ?? '') : ($tc['type'] ?? ''),
                'function' => [
                    'name' => is_object($tc) ? (is_object($tc->function) ? $tc->function->name : ($tc->function['name'] ?? '')) : ($tc['function']['name'] ?? ''),
                    'arguments' => is_object($tc) ? (is_object($tc->function) ? $tc->function->arguments : ($tc->function['arguments'] ?? '')) : ($tc['function']['arguments'] ?? '')
                ]
            ];
        }
        $toolCalls = json_encode($serialized);
    }
    
    $stmt = $conn->prepare("
        INSERT INTO messages (session_id, role, content, tool_calls, tool_call_id, name)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sessionId, $role, $content, $toolCalls, $toolCallId, $name]);
}

function getSessionHistory(string $sessionId, int $limit = 20): array {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("
        SELECT role, content, tool_calls, tool_call_id, name 
        FROM messages 
        WHERE session_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$sessionId]);
    $rows = $stmt->fetchAll();
    
    $messages = [];
    foreach ($rows as $row) {
        $msg = [
            'role' => $row['role'],
            'content' => $row['content']
        ];
        
        if ($row['tool_calls']) {
            $msg['tool_calls'] = json_decode($row['tool_calls'], true);
        }
        if ($row['tool_call_id']) {
            $msg['tool_call_id'] = $row['tool_call_id'];
        }
        if ($row['name']) {
            $msg['name'] = $row['name'];
        }
        
        $messages[] = $msg;
    }
    
    return $messages;
}

function resetSession(string $sessionId): bool {
    $conn = getDbConnection();
    try {
        $stmt = $conn->prepare("DELETE FROM messages WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
