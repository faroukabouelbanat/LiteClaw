<?php
/**
 * VoAnh - Outils Système (version PHP sécurisée)
 */

require_once __DIR__ . '/config.php';

// Motifs de commandes bloquées (sécurité)
$BLOCKED_COMMAND_PATTERNS = [
    // Auto-termination
    '/taskkill.*python/i',
    '/taskkill.*node/i',
    '/taskkill.*liteclaw/i',
    '/kill.*python/i',
    '/kill.*node/i',
    '/pkill.*python/i',
    '/pkill.*node/i',
    
    // Destruction système
    '/rm\s+-rf\s+\//i',
    '/rmdir\s+\/s\s+\/q\s+c:/i',
    '/del\s+\/f\s+\/s\s+\/q\s+c:/i',
    '/format\s+c:/i',
    '/shutdown\s+/[srh]/i',
    '/shutdown\s+-[hrP]/i',
    
    // Corruption registre/système
    '/reg\s+delete.*hklm/i',
    '/reg\s+delete.*hkcu/i',
    
    // Attaques réseau
    '/netsh.*firewall.*disable/i'
];

/**
 * Vérifie si une commande est sûre
 */
function isCommandSafe($command) {
    global $BLOCKED_COMMAND_PATTERNS;
    
    foreach ($BLOCKED_COMMAND_PATTERNS as $pattern) {
        if (preg_match($pattern, $command)) {
            return [
                'safe' => false,
                'reason' => "🚫 BLOQUÉ: Commande correspond au motif dangereux '$pattern'"
            ];
        }
    }
    
    return ['safe' => true, 'reason' => ''];
}

/**
 * Exécute une commande shell (limité pour Hostinger)
 */
function executeCommand($command) {
    // Vérification de sécurité
    $check = isCommandSafe($command);
    if (!$check['safe']) {
        return $check['reason'];
    }
    
    try {
        $system = PHP_OS_FAMILY;
        
        if ($system === 'Windows') {
            // PowerShell - limité sur Hostinger mutualisé
            // On utilise shell_exec avec désactivation des erreurs
            $output = shell_exec('powershell -Command "' . escapeshellarg($command) . '" 2>&1');
        } else {
            // Linux/Mac - Bash
            $output = shell_exec(escapeshellcmd($command) . ' 2>&1');
        }
        
        return $output ?: 'Aucune sortie';
        
    } catch (Exception $e) {
        return 'Échec exécution: ' . $e->getMessage();
    }
}

/**
 * Obtient les informations système
 */
function getSystemInfo() {
    $info = [];
    $info[] = "## Informations Système";
    $info[] = "- **Système**: " . PHP_OS;
    $info[] = "- **Version PHP**: " . phpversion();
    $info[] = "- **Mémoire allouée**: " . ini_get('memory_limit');
    
    // Extensions disponibles
    $extensions = get_loaded_extensions();
    $info[] = "- **Extensions principales**: " . implode(', ', array_slice($extensions, 0, 10)) . '...';
    
    // Tentative d'obtenir la résolution (si possible)
    if (function_exists('shell_exec')) {
        if (PHP_OS_FAMILY === 'Windows') {
            $res = shell_exec('powershell -Command "(Get-WmiObject Win32_VideoController).CurrentHorizontalResolution" 2>&1');
            if ($res) {
                $info[] = "- **Résolution écran**: ~" . trim($res) . ' pixels';
            }
        }
    }
    
    return implode("\n", $info);
}

/**
 * Récupère le contenu d'une URL avec cURL
 */
function fetchUrlContent($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return "Erreur: URL invalide";
    }
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 VoAnh/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($error) {
        return "Erreur cURL: $error";
    }
    
    if ($httpCode >= 400) {
        return "Erreur HTTP: $httpCode";
    }
    
    // Nettoyer le HTML
    $text = strip_tags($response);
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Limiter à 10000 caractères
    return substr($text, 0, 10000);
}

/**
 * Télécharge une compétence (skill)
 */
function downloadSkill($url, $skillName) {
    $skillsDir = SKILLS_DIR;
    
    if (!is_dir($skillsDir)) {
        mkdir($skillsDir, DIR_PERMISSIONS, true);
    }
    
    $content = fetchUrlContent($url);
    
    if (strpos($content, 'Erreur') === 0) {
        return "Erreur téléchargement: $content";
    }
    
    $filePath = $skillsDir . '/' . $skillName . '.md';
    file_put_contents($filePath, $content);
    chmod($filePath, FILE_PERMISSIONS);
    
    return "Compétence '$skillName' téléchargée avec succès";
}

/**
 * Lit une compétence locale
 */
function getSkillContent($skillName) {
    $filePath = SKILLS_DIR . '/' . $skillName . '.md';
    
    if (!file_exists($filePath)) {
        return "Compétence '$skillName' introuvable";
    }
    
    return file_get_contents($filePath);
}

/**
 * Liste les compétences disponibles
 */
function listSkills() {
    $skillsDir = SKILLS_DIR;
    
    if (!is_dir($skillsDir)) {
        return [];
    }
    
    $files = scandir($skillsDir);
    $skills = [];
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
            $skills[] = pathinfo($file, PATHINFO_FILENAME);
        }
    }
    
    return $skills;
}

/**
 * Outil pour mettre à jour la mémoire SOUL
 */
function updateSoulTool($content) {
    require_once __DIR__ . '/memory.php';
    updateSoulMemory($content);
    return "SOUL mise à jour avec succès";
}

/**
 * Outil pour mettre à jour la PERSONALITY
 */
function updatePersonalityTool($content) {
    require_once __DIR__ . '/memory.php';
    updatePersonalityMemory($content);
    return "PERSONALITY mise à jour avec succès";
}

/**
 * Outil pour mettre à jour SUBCONSCIOUS
 */
function updateSubconsciousTool($content) {
    require_once __DIR__ . '/memory.php';
    updateSubconsciousMemory($content);
    return "SUBCONSCIOUS mise à jour avec succès";
}

/**
 * Outil pour mettre à jour LEARNING
 */
function updateLearningTool($content) {
    require_once __DIR__ . '/memory.php';
    updateLearningMemory($content);
    return "LEARNING mise à jour avec succès";
}
