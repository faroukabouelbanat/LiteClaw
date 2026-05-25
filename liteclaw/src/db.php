<?php
/**
 * LiteClaw Database - PHP Version
 * Gestion de la base de données SQLite pour la mémoire
 */

require_once __DIR__ . '/config.php';

function getDbFile(): string {
    global $settings;
    return $settings->WORK_DIR . '/liteclaw_memory.db';
}

function getDbConnection(): PDO {
    $dbFile = getDbFile();
    $dir = dirname($dbFile);
    
    // Créer le dossier parent s'il n'existe pas
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $dsn = 'sqlite:' . $dbFile;
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    return $pdo;
}

function initDb(): void {
    $conn = getDbConnection();
    
    // Table sessions
    $conn->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            session_id TEXT PRIMARY KEY,
            parent_session_id TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Table messages
    $conn->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT,
            role TEXT,
            content TEXT,
            tool_calls TEXT,
            tool_call_id TEXT,
            name TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(session_id) REFERENCES sessions(session_id)
        )
    ");
    
    // Table cron_jobs
    $conn->exec("
        CREATE TABLE IF NOT EXISTS cron_jobs (
            id TEXT PRIMARY KEY,
            name TEXT,
            schedule_type TEXT,
            schedule_value TEXT,
            task TEXT,
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_run DATETIME
        )
    ");
}

// Initialiser la base de données au chargement du module
initDb();
