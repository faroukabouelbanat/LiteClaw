<?php
/**
 * VoAnh - Configuration Principale
 * Portail PHP/SQLite avec Mistral AI
 */

// Empêcher l'exécution directe
if (!defined('VOANH_ROOT')) {
    define('VOANH_ROOT', __DIR__);
}

// === CONFIGURATION DE BASE ===

// Clés API Mistral par défaut (rotation automatique)
define('DEFAULT_MISTRAL_API_KEYS', [
    '5qaRTjaH8Rake',
    'o3rG1zaShytu',
    'vEzQaFruXkF'
]);

// Endpoint Mistral AI
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

// Timeout pour les requêtes cURL (secondes)
define('MISTRAL_TIMEOUT', 30);

// === MODÈLES MISTRAL DISPONIBLES ===
$MODELS = [
    // Code & Développement
    'codestral-2508' => [
        'category' => 'code',
        'description' => 'Auto-complétion en temps réel, FIM optimisé'
    ],
    'devstral-2512' => [
        'category' => 'code',
        'description' => 'Architectures logicielles, DevOps, refactoring lourd'
    ],
    'devstral-medium-2507' => [
        'category' => 'code',
        'description' => 'Débogage quotidien, équilibre performance/prix'
    ],
    'devstral-small-2507' => [
        'category' => 'code',
        'description' => 'Tests unitaires, CI/CD, micro-tâches'
    ],
    
    // Raisonnement & Haute Performance
    'mistral-large-2512' => [
        'category' => 'flagship',
        'description' => 'État de l\'art, raisonnement logique, contexte massif'
    ],
    'mistral-large-2411' => [
        'category' => 'flagship',
        'description' => 'Version stable précédente, workflows entreprise'
    ],
    
    // Modèles Intermédiaires
    'mistral-medium-2508' => [
        'category' => 'medium',
        'description' => 'Tâches administratives complexes, analyse textuelle'
    ],
    'mistral-medium-2505' => [
        'category' => 'medium',
        'description' => 'RAG, synthèse de documents taille moyenne'
    ],
    
    // Vitesse & Automatisation
    'mistral-small-2603' => [
        'category' => 'small',
        'description' => 'Extraction de données, scraping API, haut débit'
    ],
    'mistral-small-2506' => [
        'category' => 'small',
        'description' => 'Classification, tagging, clustering, routage'
    ],
    
    // Agents Spécialisés
    'magistral-medium-2509' => [
        'category' => 'agent',
        'description' => 'Orchestration multi-agents, prise de décision'
    ],
    'magistral-small-2509' => [
        'category' => 'agent',
        'description' => 'Routage rapide dans architecture multi-agents'
    ],
    
    // Créativité
    'labs-mistral-small-creative' => [
        'category' => 'creative',
        'description' => 'Storytelling, scriptwriting, brainstorming'
    ],
    
    // Vision
    'pixtral-large-2411' => [
        'category' => 'vision',
        'description' => 'Analyse d\'images complexes, UI, plans, diagrammes'
    ],
    'pixtral-12b-2409' => [
        'category' => 'vision',
        'description' => 'OCR rapide, détection d\'objets, sous-titrage'
    ],
    
    // Edge Computing
    'ministral-14b-2512' => [
        'category' => 'edge',
        'description' => 'Modèle compact puissant, local/cloud léger'
    ],
    'ministral-8b-2512' => [
        'category' => 'edge',
        'description' => 'Applications mobiles, serveur embarqué'
    ],
    'ministral-3b-2512' => [
        'category' => 'edge',
        'description' => 'Ultra-léger, vitesse absolue, RAM minimale'
    ],
    
    // Audio
    'voxtral-small-2507' => [
        'category' => 'audio',
        'description' => 'Analyse sémantique audio fine, intonations'
    ],
    'voxtral-mini-2507' => [
        'category' => 'audio',
        'description' => 'Traitement rapide flux audio, commandes'
    ]
];

// Modèle par défaut
define('DEFAULT_MODEL', 'mistral-medium-2508');

// === CHEMINS ET DOSSIERS ===

// Dossier de travail principal
define('WORK_DIR', VOANH_ROOT . '/data');

// Dossiers spécialisés
define('CONFIG_DIR', VOANH_ROOT . '/config');
define('SESSIONS_DIR', WORK_DIR . '/sessions');
define('SCREENSHOTS_DIR', WORK_DIR . '/screenshots');
define('SKILLS_DIR', VOANH_ROOT . '/skills');
define('LOGS_DIR', VOANH_ROOT . '/logs');
define('DB_FILE', WORK_DIR . '/voanh.db');

// Permissions (respect des limitations Hostinger)
define('DIR_PERMISSIONS', 0755);
define('FILE_PERMISSIONS', 0644);

// === GESTION DES ERREURS ===

// Mode débogage (à false en production)
define('DEBUG_MODE', true);

// Journalisation des erreurs
ini_set('log_errors', true);
ini_set('error_log', LOGS_DIR . '/error.log');

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// === SESSIONS PHP ===

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === FONCTIONS UTILITAIRES ===

/**
 * Vérifie et crée les dossiers nécessaires
 */
function ensure_directories() {
    $dirs = [
        WORK_DIR,
        CONFIG_DIR,
        SESSIONS_DIR,
        SCREENSHOTS_DIR,
        SKILLS_DIR,
        LOGS_DIR
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, DIR_PERMISSIONS, true);
        }
    }
    
    // Créer fichier de log vide si inexistant
    $log_file = LOGS_DIR . '/error.log';
    if (!file_exists($log_file)) {
        touch($log_file);
        chmod($log_file, FILE_PERMISSIONS);
    }
}

/**
 * Nettoie une entrée utilisateur
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Génère un token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirection sécurisée
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Réponse JSON standardisée
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Log une erreur
 */
function log_error($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message";
    
    if (!empty($context)) {
        $log_entry .= ' | Context: ' . json_encode($context);
    }
    
    $log_entry .= PHP_EOL;
    
    file_put_contents(LOGS_DIR . '/error.log', $log_entry, FILE_APPEND | LOCK_EX);
}

// Initialisation
ensure_directories();
