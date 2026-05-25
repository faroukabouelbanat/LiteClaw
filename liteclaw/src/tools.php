<?php
/**
 * LiteClaw Tools - PHP Version
 * Outils système et commandes shell sécurisées
 */

class LiteClawTools {
    /**
     * Commandes bloquées pour la sécurité
     */
    private static array $BLOCKED_COMMAND_PATTERNS = [
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
    
    /**
     * Vérifier si une commande est sûre
     */
    public static function isCommandSafe(string $command): array {
        foreach (self::$BLOCKED_COMMAND_PATTERNS as $pattern) {
            if (preg_match($pattern, $command)) {
                return [false, "🚫 BLOCKED: Command matches dangerous pattern. Self-termination and destructive commands are not allowed."];
            }
        }
        return [true, ""];
    }
    
    /**
     * Exécuter une commande shell
     */
    public static function executeCommand(string $command): string {
        // Vérification de sécurité
        [$isSafe, $blockReason] = self::isCommandSafe($command);
        if (!$isSafe) {
            return $blockReason;
        }
        
        try {
            $projectRoot = "d:\\openclaw_lite";
            
            // Créer le dossier s'il n'existe pas
            if (!file_exists($projectRoot)) {
                mkdir($projectRoot, 0755, true);
            }
            
            $output = '';
            $error = '';
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows - PowerShell
                $tempScript = $projectRoot . "\\temp_" . bin2hex(random_bytes(4)) . ".ps1";
                file_put_contents($tempScript, $command);
                
                $descriptorspec = [
                    0 => ["pipe", "r"],
                    1 => ["pipe", "w"],
                    2 => ["pipe", "w"]
                ];
                
                $process = proc_open(
                    "powershell -ExecutionPolicy Bypass -File \"$tempScript\"",
                    $descriptorspec,
                    $pipes,
                    $projectRoot
                );
                
                if (is_resource($process)) {
                    $output = stream_get_contents($pipes[1]);
                    $error = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                }
                
                // Nettoyer le fichier temporaire
                @unlink($tempScript);
            } else {
                // Linux/Mac
                $output = shell_exec("cd " . escapeshellarg($projectRoot) . " && " . escapeshellcmd($command));
            }
            
            $result = $output ?? '';
            if ($error) {
                $result .= "\nError:\n" . $error;
            }
            
            return $result ?: "Command executed (no output)";
            
        } catch (Exception $e) {
            return "Failed to execute command: " . $e->getMessage();
        }
    }
    
    /**
     * Obtenir les informations système
     */
    public static function getSystemInfo(): string {
        $info = [];
        $info[] = "## System Information";
        $info[] = "- **Operating System**: " . php_uname('s') . " " . php_uname('r');
        
        // Navigateurs sur Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $browsers = [];
            $paths = [
                "Chrome" => [
                    getenv('ProgramFiles') . "\\Google\\Chrome\\Application\\chrome.exe",
                    getenv('ProgramFiles(x86)') . "\\Google\\Chrome\\Application\\chrome.exe",
                    getenv('LocalAppData') . "\\Google\\Chrome\\Application\\chrome.exe"
                ],
                "Edge" => [
                    getenv('ProgramFiles(x86)') . "\\Microsoft\\Edge\\Application\\msedge.exe",
                    getenv('ProgramFiles') . "\\Microsoft\\Edge\\Application\\msedge.exe"
                ],
                "Firefox" => [
                    getenv('ProgramFiles') . "\\Mozilla Firefox\\firefox.exe",
                    getenv('ProgramFiles(x86)') . "\\Mozilla Firefox\\firefox.exe"
                ],
                "Brave" => [
                    getenv('ProgramFiles') . "\\BraveSoftware\\Brave-Browser\\Application\\brave.exe",
                    getenv('LocalAppData') . "\\BraveSoftware\\Brave-Browser\\Application\\brave.exe"
                ]
            ];
            
            foreach ($paths as $name => $possiblePaths) {
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        $browsers[] = "  - **$name**: `$path`";
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
}

// Helper functions
function executeCommand(string $command): string {
    return LiteClawTools::executeCommand($command);
}

function getSystemInfo(): string {
    return LiteClawTools::getSystemInfo();
}
