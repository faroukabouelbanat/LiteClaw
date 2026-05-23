<?php
/**
 * VoAnh - Configuration Principale
 * Clone de Claude AI avec agents autonomes et auto-renforcement
 */

// Empêcher l'exécution directe
if (!defined('VOANH_INIT')) {
    define('VOANH_INIT', true);
}

// Chemins
define('ROOT_PATH', dirname(__DIR__));
define('CORE_PATH', ROOT_PATH . '/core');
define('API_PATH', ROOT_PATH . '/api');
define('INTERFACE_PATH', ROOT_PATH . '/interface');
define('DATA_PATH', ROOT_PATH . '/data');
define('SANDBOX_PATH', ROOT_PATH . '/sandbox');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// Base de données
define('DB_FILE', DATA_PATH . '/voanh.sqlite');
define('DB_LOCK_FILE', DATA_PATH . '/voanh.lock');

// API Keys Mistral (3 clés avec rotation)
define('DEFAULT_MISTRAL_API_KEYS', [
    '5qaRTjaH8Rake',
    'o3rG1zaShytu',
    'vEzQaFruXkF'
]);

// Endpoint API Mistral
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

// Modèles Mistral organisés par catégorie
define('MISTRAL_MODELS', [
    'code' => [
        ['id' => 'codestral-2508', 'name' => 'Code Master Ultimate', 'desc' => 'Auto-complétion temps réel, FIM'],
        ['id' => 'devstral-2512', 'name' => 'Dev Agent Pro', 'desc' => 'Architecture, déploiement, refactoring'],
        ['id' => 'devstral-medium-2507', 'name' => 'Dev Agent Medium', 'desc' => 'Débogage quotidien, patterns complexes'],
        ['id' => 'devstral-small-2507', 'name' => 'Dev Agent Light', 'desc' => 'Micro-tâches, tests unitaires, CI/CD']
    ],
    'flagship' => [
        ['id' => 'mistral-large-2512', 'name' => 'Mistral Brain Ultra', 'desc' => 'Raisonnement logique, contextes massifs'],
        ['id' => 'mistral-large-2411', 'name' => 'Mistral Brain Legacy', 'desc' => 'Version stable, workflows entreprise']
    ],
    'medium' => [
        ['id' => 'mistral-medium-2508', 'name' => 'Corporate Engine Pro', 'desc' => 'Tâches admin, analyse textuelle'],
        ['id' => 'mistral-medium-2505', 'name' => 'Corporate Engine Standard', 'desc' => 'RAG, synthèse documents']
    ],
    'small' => [
        ['id' => 'mistral-small-2603', 'name' => 'Fast Automate Turbo', 'desc' => 'Extraction masse, scraping API'],
        ['id' => 'mistral-small-2506', 'name' => 'Fast Automate Standard', 'desc' => 'Classification, tagging, clustering']
    ],
    'agent' => [
        ['id' => 'magistral-medium-2509', 'name' => 'Agent Router Medium', 'desc' => 'Orchestration multi-agents'],
        ['id' => 'magistral-small-2509', 'name' => 'Agent Router Small', 'desc' => 'Routage rapide prompts']
    ],
    'creative' => [
        ['id' => 'labs-mistral-small-creative', 'name' => 'Creative Writer', 'desc' => 'Storytelling, brainstorming']
    ],
    'vision' => [
        ['id' => 'pixtral-large-2411', 'name' => 'Vision Analyzer Max', 'desc' => 'UI, plans, diagrammes'],
        ['id' => 'pixtral-12b-2409', 'name' => 'Vision Analyzer Light', 'desc' => 'OCR, détection objets']
    ],
    'edge' => [
        ['id' => 'ministral-14b-2512', 'name' => 'Local Engine Heavy', 'desc' => 'Modèle compact puissant'],
        ['id' => 'ministral-8b-2512', 'name' => 'Local Engine Medium', 'desc' => 'All-rounder mobile'],
        ['id' => 'ministral-3b-2512', 'name' => 'Local Engine Micro', 'desc' => 'Ultra-léger, commande vocale']
    ],
    'audio' => [
        ['id' => 'voxtral-small-2507', 'name' => 'Audio Core Small', 'desc' => 'Analyse sémantique audio'],
        ['id' => 'voxtral-mini-2507', 'name' => 'Audio Core Mini', 'desc' => 'Traitement flux rapide']
    ]
]);

// Configuration système
define('MAX_EXECUTION_TIME', 120);
define('MEMORY_LIMIT', '256M');
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('SESSION_LIFETIME', 3600 * 24); // 24 heures
define('RATE_LIMIT_REQUESTS', 100); // requêtes par minute
define('RATE_LIMIT_WINDOW', 60); // secondes

// Sandbox
define('SANDBOX_ENABLED', true);
define('SANDBOX_TIMEOUT', 30);
define('SANDBOX_MEMORY_LIMIT', '128M');

// Logs
define('LOG_FILE', DATA_PATH . '/voanh.log');
define('LOG_LEVEL', 3); // 0: none, 1: error, 2: warning, 3: info, 4: debug

// Sécurité
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Auto-renforcement
define('AUTO_LEARNING_ENABLED', true);
define('LEARNING_THRESHOLD', 0.8); // Seuil de confiance pour apprentissage
define('MEMORY_CONSOLIDATION_INTERVAL', 3600); // 1 heure

// Agents
define('MASTER_AGENT_MODEL', 'mistral-large-2512');
define('CODE_AGENT_MODEL', 'devstral-2512');
define('VISION_AGENT_MODEL', 'pixtral-large-2411');
define('PLANNER_AGENT_MODEL', 'magistral-medium-2509');
define('CREATIVE_AGENT_MODEL', 'labs-mistral-small-creative');

// Création des dossiers si inexistants
$directories = [DATA_PATH, SANDBOX_PATH, ASSETS_PATH];
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

// Fonction de logging
function voanh_log($message, $level = 3) {
    if ($level > LOG_LEVEL) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $levels = [0 => 'NONE', 1 => 'ERROR', 2 => 'WARNING', 3 => 'INFO', 4 => 'DEBUG'];
    $log_entry = "[$timestamp] [" . $levels[$level] . "] $message\n";
    
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

// Gestion des erreurs
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    voanh_log("Error [$errno]: $errstr in $errfile on line $errline", 1);
    if (error_reporting() & $errno) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
});

set_exception_handler(function($exception) {
    voanh_log("Uncaught exception: " . $exception->getMessage(), 1);
    if (!headers_sent()) {
        http_response_code(500);
        echo json_encode(['error' => 'Une erreur interne est survenue']);
    }
});

voanh_log("VoAnh initialized", 3);
