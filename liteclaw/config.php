<?php
/**
 * LiteClaw - Clone de Claude AI en PHP
 * Configuration Principale
 * 
 * Compatible hébergement mutualisé Hostinger
 */

// Empêcher l'exécution directe
if (!defined('LITECLAW_INIT')) {
    define('LITECLAW_INIT', true);
}

// Chemins (relatifs, compatibles Hostinger)
define('ROOT_PATH', __DIR__);
define('CORE_PATH', ROOT_PATH . '/core');
define('API_PATH', ROOT_PATH . '/api');
define('INTERFACE_PATH', ROOT_PATH . '/interface');
define('DATA_PATH', ROOT_PATH . '/data');
define('SANDBOX_PATH', ROOT_PATH . '/sandbox');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Base de données SQLite
define('DB_FILE', DATA_PATH . '/liteclaw.sqlite');

// API Keys Mistral (3 clés avec rotation - 1 milliard de tokens/mois chacune)
define('DEFAULT_MISTRAL_API_KEYS', [
    '5qaRTjH8Rake',
    'o3rG1RShytu',
    'vEzQMKDjFruXkF'
]);

// Endpoint API Mistral
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

// Modèles Mistral organisés par catégorie avec sélection intelligente
define('MISTRAL_MODELS', [
    'code' => [
        ['id' => 'codestral-2508', 'name' => 'Code Master Ultimate', 'desc' => 'Auto-complétion temps réel, FIM', 'use_case' => 'completion'],
        ['id' => 'devstral-2512', 'name' => 'Dev Agent Pro', 'desc' => 'Architecture, déploiement, refactoring', 'use_case' => 'architecture'],
        ['id' => 'devstral-medium-2507', 'name' => 'Dev Agent Medium', 'desc' => 'Débogage quotidien, patterns complexes', 'use_case' => 'debug'],
        ['id' => 'devstral-small-2507', 'name' => 'Dev Agent Light', 'desc' => 'Micro-tâches, tests unitaires, CI/CD', 'use_case' => 'testing']
    ],
    'flagship' => [
        ['id' => 'mistral-large-2512', 'name' => 'Mistral Brain Ultra', 'desc' => 'Raisonnement logique, contextes massifs', 'use_case' => 'reasoning'],
        ['id' => 'mistral-large-2411', 'name' => 'Mistral Brain Legacy', 'desc' => 'Version stable, workflows entreprise', 'use_case' => 'enterprise']
    ],
    'medium' => [
        ['id' => 'mistral-medium-2508', 'name' => 'Corporate Engine Pro', 'desc' => 'Tâches admin, analyse textuelle', 'use_case' => 'business'],
        ['id' => 'mistral-medium-2505', 'name' => 'Corporate Engine Standard', 'desc' => 'RAG, synthèse documents', 'use_case' => 'synthesis']
    ],
    'small' => [
        ['id' => 'mistral-small-2603', 'name' => 'Fast Automate Turbo', 'desc' => 'Extraction masse, scraping API', 'use_case' => 'extraction'],
        ['id' => 'mistral-small-2506', 'name' => 'Fast Automate Standard', 'desc' => 'Classification, tagging, clustering', 'use_case' => 'classification']
    ],
    'agent' => [
        ['id' => 'magistral-medium-2509', 'name' => 'Agent Router Medium', 'desc' => 'Orchestration multi-agents', 'use_case' => 'orchestration'],
        ['id' => 'magistral-small-2509', 'name' => 'Agent Router Small', 'desc' => 'Routage rapide prompts', 'use_case' => 'routing']
    ],
    'creative' => [
        ['id' => 'labs-mistral-small-creative', 'name' => 'Creative Writer', 'desc' => 'Storytelling, brainstorming', 'use_case' => 'creative']
    ],
    'vision' => [
        ['id' => 'pixtral-large-2411', 'name' => 'Vision Analyzer Max', 'desc' => 'UI, plans, diagrammes', 'use_case' => 'analysis'],
        ['id' => 'pixtral-12b-2409', 'name' => 'Vision Analyzer Light', 'desc' => 'OCR, détection objets', 'use_case' => 'ocr']
    ],
    'edge' => [
        ['id' => 'ministral-14b-2512', 'name' => 'Local Engine Heavy', 'desc' => 'Modèle compact puissant', 'use_case' => 'compact'],
        ['id' => 'ministral-8b-2512', 'name' => 'Local Engine Medium', 'desc' => 'All-rounder mobile', 'use_case' => 'mobile'],
        ['id' => 'ministral-3b-2512', 'name' => 'Local Engine Micro', 'desc' => 'Ultra-léger, commande vocale', 'use_case' => 'voice']
    ],
    'audio' => [
        ['id' => 'voxtral-small-2507', 'name' => 'Audio Core Small', 'desc' => 'Analyse sémantique audio', 'use_case' => 'audio_analysis'],
        ['id' => 'voxtral-mini-2507', 'name' => 'Audio Core Mini', 'desc' => 'Traitement flux rapide', 'use_case' => 'audio_stream']
    ]
]);

// Modèle par défaut
define('DEFAULT_MODEL', 'mistral-large-2512');

// Configuration système (optimisée pour mutualisé)
define('MAX_EXECUTION_TIME', 60);
define('MEMORY_LIMIT', '256M');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('SESSION_LIFETIME', 3600 * 24); // 24 heures
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60);

// Logs
define('LOG_FILE', LOGS_PATH . '/liteclaw.log');
define('LOG_LEVEL', 3);

// Sécurité
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);

// Création des dossiers si inexistants (permissions 0755 pour Hostinger)
$directories = [DATA_PATH, SANDBOX_PATH, ASSETS_PATH, LOGS_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Fonction de logging (écriture fichier, pas d'affichage écran)
function liteclaw_log($message, $level = 3) {
    if ($level > LOG_LEVEL) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $levels = [0 => 'NONE', 1 => 'ERROR', 2 => 'WARNING', 3 => 'INFO', 4 => 'DEBUG'];
    $log_entry = "[$timestamp] [" . $levels[$level] . "] $message\n";
    
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

// Gestion des erreurs (pas d'affichage brut en production)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    liteclaw_log("Error [$errno]: $errstr in $errfile on line $errline", 1);
    if (error_reporting() & $errno) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
});

set_exception_handler(function($exception) {
    liteclaw_log("Uncaught exception: " . $exception->getMessage(), 1);
    if (!headers_sent()) {
        http_response_code(500);
        echo json_encode(['error' => 'Une erreur interne est survenue']);
    }
});

liteclaw_log("LiteClaw initialized", 3);
