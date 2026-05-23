<?php
/**
 * LiteClaw - Gestion de la Base de Données SQLite
 * Compatible hébergement mutualisé Hostinger
 */

require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            // Connexion SQLite avec options optimisées pour mutualisé
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Optimisations SQLite (compatibles mutualisé)
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
            $this->pdo->exec('PRAGMA cache_size=10000');
            $this->pdo->exec('PRAGMA temp_store=MEMORY');
            
            $this->initializeTables();
            liteclaw_log("Database connection established", 3);
        } catch (PDOException $e) {
            liteclaw_log("Database connection failed: " . $e->getMessage(), 1);
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function initializeTables() {
        $queries = [
            // Utilisateurs
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                mistral_api_key TEXT,
                role TEXT DEFAULT 'user',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                login_attempts INTEGER DEFAULT 0,
                locked_until DATETIME,
                settings TEXT DEFAULT '{}',
                onboarding_completed INTEGER DEFAULT 0
            )",
            
            // Sessions
            "CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token TEXT UNIQUE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip_address TEXT,
                user_agent TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Conversations
            "CREATE TABLE IF NOT EXISTS conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT,
                model_used TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_archived INTEGER DEFAULT 0,
                metadata TEXT DEFAULT '{}',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Messages
            "CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id INTEGER NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                model_used TEXT,
                tokens_used INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                metadata TEXT DEFAULT '{}',
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
            )",
            
            // Index pour performance
            "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token)",
            "CREATE INDEX IF NOT EXISTS idx_conversations_user ON conversations(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id)"
        ];
        
        foreach ($queries as $query) {
            $this->pdo->exec($query);
        }
        
        liteclaw_log("Database tables initialized", 3);
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            liteclaw_log("Query failed: " . $e->getMessage(), 1);
            throw $e;
        }
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = :$key";
        }
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollBack();
    }
}
