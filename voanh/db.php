<?php
/**
 * VoAnh - Gestion de la Base de Données SQLite
 */

require_once __DIR__ . '/config.php';

/**
 * Classe de gestion de la base de données
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct() {
        try {
            // Création de la connexion PDO
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Initialiser les tables
            $this->initTables();
        } catch (PDOException $e) {
            log_error('Erreur de connexion à la base de données: ' . $e->getMessage());
            die('Erreur de connexion à la base de données');
        }
    }
    
    /**
     * Récupère l'instance unique
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise les tables de la base de données
     */
    private function initTables() {
        // Table des utilisateurs
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                mistral_api_key TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT 1
            )
        ');
        
        // Table des sessions
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS sessions (
                session_id TEXT PRIMARY KEY,
                user_id INTEGER,
                parent_session_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id)
            )
        ');
        
        // Index pour les sessions
        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id)
        ');
        
        // Table des messages
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                tool_calls TEXT,
                tool_call_id TEXT,
                name TEXT,
                model_used TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES sessions(session_id)
            )
        ');
        
        // Index pour les messages
        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_messages_session ON messages(session_id)
        ');
        $this->pdo->exec('
            CREATE INDEX IF NOT EXISTS idx_messages_timestamp ON messages(timestamp)
        ');
        
        // Table des tâches cron
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS cron_jobs (
                id TEXT PRIMARY KEY,
                user_id INTEGER,
                name TEXT NOT NULL,
                schedule_type TEXT NOT NULL,
                schedule_value TEXT NOT NULL,
                task TEXT NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_run DATETIME,
                FOREIGN KEY(user_id) REFERENCES users(id)
            )
        ');
        
        // Table des sub-agents
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS sub_agents (
                id TEXT PRIMARY KEY,
                session_id TEXT NOT NULL,
                name TEXT NOT NULL,
                status TEXT DEFAULT \'idle\',
                last_result TEXT,
                task_history TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Table des compétences (skills)
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS skills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                content TEXT,
                url_source TEXT,
                downloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Table des préférences utilisateur
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS user_preferences (
                user_id INTEGER PRIMARY KEY,
                default_model TEXT DEFAULT \'mistral-medium-2508\',
                notifications_enabled BOOLEAN DEFAULT 1,
                FOREIGN KEY(user_id) REFERENCES users(id)
            )
        ');
        
        // Table de rotation des clés API (suivi d'utilisation)
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS api_key_rotation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                api_key TEXT NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                requests_count INTEGER DEFAULT 0,
                last_used DATETIME,
                error_count INTEGER DEFAULT 0,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Initialiser les clés API par défaut
        $this->initApiKeys();
        
        // Créer le dossier config s'il n'existe pas et y placer les fichiers de mémoire
        $this->initMemoryFiles();
    }
    
    /**
     * Initialise les clés API dans la table de rotation
     */
    private function initApiKeys() {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM api_key_rotation');
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $stmt = $this->pdo->prepare('INSERT INTO api_key_rotation (api_key) VALUES (?)');
            foreach (DEFAULT_MISTRAL_API_KEYS as $key) {
                $stmt->execute([$key]);
            }
        }
    }
    
    /**
     * Initialise les fichiers de mémoire (SOUL, PERSONALITY, etc.)
     */
    private function initMemoryFiles() {
        $files = [
            'SOUL.md' => "# SOUL Memory\n\nMémoire à long terme sur l'utilisateur et ses préférences.\n",
            'PERSONALITY.md' => "# PERSONALITY\n\n## Traits\n- Serviable et amical\n- Précis et détaillé\n- Proactif dans la résolution de problèmes\n\n## Règles Internes\n- Toujours vérifier avant d'agir\n- Demander confirmation pour les actions critiques\n",
            'SUBCONSCIOUS.md' => "# SUBCONSCIOUS\n\nIdées innovantes, motifs d'erreurs, et expériences apprises.\n",
            'LEARNING.md' => "# LEARNING\n\nMeilleures pratiques, leçons apprises, et stratégies d'auto-organisation.\n",
            'AGENT.md' => "# AGENT PROFILE\n\nTu es VoAnh, un assistant IA puissant et autonome.\n\n## Directives de Base\n- Tu as accès à un ordinateur et peux exécuter des commandes\n- Tu dois être prudent avec les commandes système\n- Tu apprends et t'améliores continuellement\n",
            'HEARTBEAT.md' => "---\ninterval_seconds: 3600\nenabled: false\n---\n\n# Tâches Heartbeat\n\n- Vérifier les logs système\n- Nettoyer les fichiers temporaires\n- Mettre à jour les statistiques\n"
        ];
        
        foreach ($files as $filename => $content) {
            $filepath = CONFIG_DIR . '/' . $filename;
            if (!file_exists($filepath)) {
                file_put_contents($filepath, $content);
                chmod($filepath, FILE_PERMISSIONS);
            }
        }
    }
    
    /**
     * Exécute une requête préparée
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            log_error('Erreur SQL: ' . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            throw $e;
        }
    }
    
    /**
     * Récupère un seul enregistrement
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Récupère tous les enregistrements
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insère un enregistrement et retourne l'ID
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Met à jour des enregistrements
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = :$column";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $this->query($sql, array_merge($data, $whereParams));
    }
    
    /**
     * Supprime des enregistrements
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
    }
}

// Fonction helper pour accéder rapidement à la DB
function getDb() {
    return Database::getInstance();
}
