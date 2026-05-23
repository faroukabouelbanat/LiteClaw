<?php
/**
 * VoAnh - Gestion de la Base de Données SQLite
 * Schéma complet pour agents autonomes, mémoire, et auto-renforcement
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            // Connexion SQLite avec options optimisées
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
            $this->pdo->exec('PRAGMA cache_size=10000');
            $this->pdo->exec('PRAGMA temp_store=MEMORY');
            
            $this->initializeTables();
            voanh_log("Database connection established", 3);
        } catch (PDOException $e) {
            voanh_log("Database connection failed: " . $e->getMessage(), 1);
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
            
            // Mémoire SOUL (identité fondamentale)
            "CREATE TABLE IF NOT EXISTS soul_memory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                identity_profile TEXT,
                core_values TEXT,
                personality_traits TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Mémoire PERSONALITY (préférences comportementales)
            "CREATE TABLE IF NOT EXISTS personality_memory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                communication_style TEXT,
                response_preferences TEXT,
                tone_settings TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Mémoire SUBCONSCIOUS (patterns implicites)
            "CREATE TABLE IF NOT EXISTS subconscious_memory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                implicit_patterns TEXT,
                behavioral_tendencies TEXT,
                learned_associations TEXT,
                confidence_scores TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Mémoire LEARNING (connaissances acquises)
            "CREATE TABLE IF NOT EXISTS learning_memory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                knowledge_base TEXT,
                skills_acquired TEXT,
                corrections_applied TEXT,
                success_patterns TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Agents
            "CREATE TABLE IF NOT EXISTS agents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                model_id TEXT NOT NULL,
                capabilities TEXT,
                status TEXT DEFAULT 'idle',
                current_task TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_active DATETIME,
                performance_metrics TEXT DEFAULT '{}',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Sous-agents
            "CREATE TABLE IF NOT EXISTS subagents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_agent_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                specialization TEXT,
                status TEXT DEFAULT 'idle',
                assigned_task TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_tasks INTEGER DEFAULT 0,
                FOREIGN KEY (parent_agent_id) REFERENCES agents(id) ON DELETE CASCADE
            )",
            
            // Tâches
            "CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                agent_id INTEGER,
                title TEXT NOT NULL,
                description TEXT,
                status TEXT DEFAULT 'pending',
                priority INTEGER DEFAULT 5,
                plan TEXT,
                steps TEXT,
                current_step INTEGER DEFAULT 0,
                result TEXT,
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME,
                completed_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
            )",
            
            // Planificateur
            "CREATE TABLE IF NOT EXISTS planner_schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                task_id INTEGER,
                schedule_type TEXT,
                cron_expression TEXT,
                next_run DATETIME,
                last_run DATETIME,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            )",
            
            // Historique navigateur (utilitaires)
            "CREATE TABLE IF NOT EXISTS browser_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                title TEXT,
                content_snapshot TEXT,
                visited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Fichiers sandbox
            "CREATE TABLE IF NOT EXISTS sandbox_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                filename TEXT NOT NULL,
                filepath TEXT NOT NULL,
                file_type TEXT,
                size INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                executed INTEGER DEFAULT 0,
                execution_result TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Onboarding
            "CREATE TABLE IF NOT EXISTS onboarding_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                step_name TEXT NOT NULL,
                step_order INTEGER NOT NULL,
                completed INTEGER DEFAULT 0,
                completed_at DATETIME,
                data TEXT DEFAULT '{}',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )",
            
            // Logs d'activité
            "CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // Index pour performance
            "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token)",
            "CREATE INDEX IF NOT EXISTS idx_conversations_user ON conversations(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status)",
            "CREATE INDEX IF NOT EXISTS idx_agents_user ON agents(user_id)"
        ];
        
        foreach ($queries as $query) {
            $this->pdo->exec($query);
        }
        
        voanh_log("Database tables initialized", 3);
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            voanh_log("Query failed: " . $e->getMessage(), 1);
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
