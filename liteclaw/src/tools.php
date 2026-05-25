<?php
/**
 * LiteClaw Tools - PHP Version
 * Outils système et commandes shell sécurisées
 */

// Patterns de commandes bloquées pour la sécurité
$BLOCKED_COMMAND_PATTERNS = [
    // Auto-destruction
    '/taskkill.*python/i',
    '/taskkill.*node/i',
    '/taskkill.*liteclaw/i',
    '/kill.*python/i',
    '/kill.*node/i',
    '/pkill.*python/i',
    '/pkill.*node/i',
    '/killall.*python/i',
    '/killall.*node/i',
    '/stop-process.*python/i',
    '/stop-process.*node/i',
    
    // Destruction système
    '/rm\s+-rf\s+\//i',
    '/rmdir\s+\/s\s+\/q\s+c:/i',
    '/del\s+\/f\s+\/s\s+\/q\s+c:/i',
    '/format\s+c:/i',
    '/shutdown\s+\/(s|r|h)/i',
    '/shutdown\s+-(h|r|P)/i',
    
    // Corruption registre/système
    '/reg\s+delete.*hklm/i',
    '/reg\s+delete.*hkcu/i',
    
    // Attaques réseau
    '/netsh.*firewall.*disable/i',
];

function isCommandSafe(string $command): array {
    global $BLOCKED_COMMAND_PATTERNS;
    
    foreach ($BLOCKED_COMMAND_PATTERNS as $pattern) {
        if (preg_match($pattern, $command)) {
            return [false, "🚫 BLOCKED: Command matches dangerous pattern. Self-termination and destructive commands are not allowed."];
        }
    }
    
    return [true, ""];
}

function executeCommand(string $command): string {
    // Vérification de sécurité
    [$isSafe, $blockReason] = isCommandSafe($command);
    if (!$isSafe) {
        return $blockReason;
    }
    
    try {
        $system = php_uname('s');
        $projectRoot = 'd:\\openclaw_lite';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($projectRoot)) {
            mkdir($projectRoot, 0755, true);
        }
        
        if ($system === 'Windows NT') {
            // Détecter les commandes complexes
            $complexityIndicators = [
                strpos($command, '@{') !== false,
                strpos($command, '| ConvertTo-Json') !== false,
                strpos($command, 'Invoke-RestMethod') !== false,
                strpos($command, 'try {') !== false,
                strlen($command) > 200,
                substr_count($command, '"') > 4,
                substr_count($command, "'") > 4,
            ];
            
            $isComplex = in_array(true, $complexityIndicators, true);
            
            if ($isComplex) {
                // Écrire dans un fichier temporaire
                $tempScript = $projectRoot . '\\temp_' . bin2hex(random_bytes(4)) . '.ps1';
                file_put_contents($tempScript, $command);
                
                $output = [];
                $returnVar = 0;
                exec("powershell -ExecutionPolicy Bypass -File " . escapeshellarg($tempScript) . " 2>&1", $output, $returnVar);
                
                // Nettoyer le fichier temporaire
                @unlink($tempScript);
                
                return implode("\n", $output);
            } else {
                // Commande simple
                $output = [];
                $returnVar = 0;
                exec("powershell -Command " . escapeshellarg($command) . " 2>&1", $output, $returnVar);
                return implode("\n", $output);
            }
        } else {
            // Linux/Mac
            $output = [];
            $returnVar = 0;
            exec($command . " 2>&1", $output, $returnVar);
            return implode("\n", $output);
        }
    } catch (Exception $e) {
        return "Failed to execute command: " . $e->getMessage();
    }
}

function getSystemInfo(): string {
    $info = [];
    $info[] = "## System Information";
    $info[] = "- **Operating System**: " . php_uname('s') . " " . php_uname('r');
    
    if (php_uname('s') === 'Windows NT') {
        $browsers = [];
        $paths = [
            'Chrome' => [
                getenv('ProgramFiles') . '\\Google\\Chrome\\Application\\chrome.exe',
                getenv('ProgramFiles(x86)') . '\\Google\\Chrome\\Application\\chrome.exe',
                getenv('LocalAppData') . '\\Google\\Chrome\\Application\\chrome.exe'
            ],
            'Edge' => [
                getenv('ProgramFiles(x86)') . '\\Microsoft\\Edge\\Application\\msedge.exe',
                getenv('ProgramFiles') . '\\Microsoft\\Edge\\Application\\msedge.exe'
            ],
            'Firefox' => [
                getenv('ProgramFiles') . '\\Mozilla Firefox\\firefox.exe',
                getenv('ProgramFiles(x86)') . '\\Mozilla Firefox\\firefox.exe'
            ]
        ];
        
        foreach ($paths as $name => $possiblePaths) {
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $browsers[] = "  - **{$name}**: `{$path}`";
                    break;
                }
            }
        }
        
        if ($browsers) {
            $info[] = "- **Available Browsers**:\n" . implode("\n", $browsers);
        } else {
            $info[] = "- **Available Browsers**: No common browsers detected via standard paths.";
        }
    }
    
    return implode("\n", $info);
}
