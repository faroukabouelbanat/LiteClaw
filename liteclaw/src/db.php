<?php
/**
 * LiteClaw Database - PHP Version
 * Gère la connexion et les opérations sur la base de données SQLite
 */

require_once __DIR__ . '/config.php';

class LiteClawDB {
    private static ?SQLite3 $connection = null;
    
    /**
     * Get the database file path
     */
    public static function getDbFile(): string {
        global $settings;
        return $settings->WORK_DIR . "/liteclaw_memory.db";
    }
    
    /**
     * Get database connection (singleton)
     */
    public static function getConnection(): SQLite3 {
        if (self::$connection === null) {
            $dbFile = self::getDbFile();
            
            // Create parent directory if missing
            $dir = dirname($dbFile);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            self::$connection = new SQLite3($dbFile);
            self::$connection->enableExceptions(true);
        }
        return self::$connection;
    }
    
    /**
     * Initialize database tables
     */
    public static function initDb(): void {
        $db = self::getConnection();
        
        // Create sessions table
        $db->exec('
            CREATE TABLE IF NOT EXISTS sessions (
                session_id TEXT PRIMARY KEY,
                parent_session_id TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Create messages table
        $db->exec('
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT,
                role TEXT,
                content TEXT,
                tool_calls TEXT,
                tool_call_id TEXT,
                name TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES sessions(session_id)
            )
        ');
        
        // Create cron_jobs table
        $db->exec('
            CREATE TABLE IF NOT EXISTS cron_jobs (
                id TEXT PRIMARY KEY,
                name TEXT,
                schedule_type TEXT,
                schedule_value TEXT,
                task TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_run TIMESTAMP
            )
        ');
    }
}

// Initialize database on load
LiteClawDB::initDb();

/**
 * Create a new session
 */
function createSession(string $sessionId, ?string $parentSessionId = null): bool {
    try {
        $db = LiteClawDB::getConnection();
        $stmt = $db->prepare('INSERT INTO sessions (session_id, parent_session_id) VALUES (:session_id, :parent_session_id)');
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        $stmt->bindValue(':parent_session_id', $parentSessionId, SQLITE3_TEXT);
        return $stmt->execute();
    } catch (Exception $e) {
        // Session likely exists
        return false;
    }
}

/**
 * List all sessions
 */
function listSessions(): array {
    try {
        $db = LiteClawDB::getConnection();
        $result = $db->query('SELECT session_id, created_at FROM sessions ORDER BY created_at DESC');
        $sessions = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $sessions[] = $row;
        }
        return $sessions;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Add a message to a session
 */
function addMessage(string $sessionId, array $message): void {
    $db = LiteClawDB::getConnection();
    
    $role = $message['role'] ?? '';
    $content = $message['content'] ?? '';
    $toolCallId = $message['tool_call_id'] ?? null;
    $name = $message['name'] ?? null;
    
    // Check last message for deduplication
    $result = $db->query("SELECT role, content, tool_call_id, name FROM messages WHERE session_id = '$sessionId' ORDER BY id DESC LIMIT 1");
    $last = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($last && 
        $last['role'] === $role && 
        $last['content'] === $content && 
        $last['tool_call_id'] === $toolCallId && 
        $last['name'] === $name) {
        return; // Duplicate message
    }
    
    // Handle tool calls serialization
    $toolCalls = null;
    if (isset($message['tool_calls']) && is_array($message['tool_calls'])) {
        $toolCallsData = [];
        foreach ($message['tool_calls'] as $tc) {
            $toolCallsData[] = [
                'id' => is_object($tc) ? ($tc->id ?? $tc['id'] ?? null) : ($tc['id'] ?? null),
                'type' => is_object($tc) ? ($tc->type ?? $tc['type'] ?? null) : ($tc['type'] ?? null),
                'function' => [
                    'name' => is_object($tc->function ?? null) ? $tc->function->name : ($tc['function']['name'] ?? null),
                    'arguments' => is_object($tc->function ?? null) ? $tc->function->arguments : ($tc['function']['arguments'] ?? null)
                ]
            ];
        }
        $toolCalls = json_encode($toolCallsData);
    }
    
    $stmt = $db->prepare('INSERT INTO messages (session_id, role, content, tool_calls, tool_call_id, name) VALUES (:session_id, :role, :content, :tool_calls, :tool_call_id, :name)');
    $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    $stmt->bindValue(':content', $content, SQLITE3_TEXT);
    $stmt->bindValue(':tool_calls', $toolCalls, SQLITE3_TEXT);
    $stmt->bindValue(':tool_call_id', $toolCallId, SQLITE3_TEXT);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->execute();
}

/**
 * Get session history
 */
function getSessionHistory(string $sessionId, int $limit = 20): array {
    $db = LiteClawDB::getConnection();
    $result = $db->query("SELECT role, content, tool_calls, tool_call_id, name FROM messages WHERE session_id = '$sessionId' ORDER BY id ASC");
    
    $messages = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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

/**
 * Reset/clear a session
 */
function resetSession(string $sessionId): bool {
    try {
        $db = LiteClawDB::getConnection();
        $stmt = $db->prepare("DELETE FROM messages WHERE session_id = :session_id");
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_TEXT);
        return $stmt->execute();
    } catch (Exception $e) {
        return false;
    }
}
