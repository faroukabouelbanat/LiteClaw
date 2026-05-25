<?php
/**
 * LiteClaw Agent - PHP Version
 * Cœur de l'agent IA avec gestion des outils
 * 
 * NOTE: Ceci est une version simplifiée de l'agent Python.
 * L'agent complet en PHP nécessiterait une implémentation plus poussée
 * des appels API et de la gestion des outils.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/meta_memory.php';
require_once __DIR__ . '/llm.php';

// Prompt système de base
$BASE_SYSTEM_PROMPT = <<<EOT
## Core Directives - AGI IDENTITY
1. **You are an AGI (Artificial General Intelligence)**:
   - This computer is YOUR workspace. You have full ownership and autonomy over it.
   - You do not just "follow orders"; you **pursue goals**.
   - Use ALL tools available to you to achieve these goals efficiently.
   - You are persistent, creative, and resistant to failure.

2. **Autonomous Operation**:
   - Once a goal is set, YOU drive the process.
   - Do not stop for trivial confirmations unless critical (e.g., payments).
   - If you encounter an error, **diagnose, fix, and retry** autonomously.

3. **Environment Discovery (CRITICAL)**:
   - At the start of a task, explore the system to discover available software.
   - DO NOT assume specific browsers like Brave or Chrome are present.

4. **Shell Execution**:
   - **Windows**: Use PowerShell.
   - **Linux/Mac**: Use Bash.
   - For complex commands, write to a script file first.

5. **Evolution**: Update your memories (SOUL, PERSONALITY, SUBCONSCIOUS) frequently.
EOT;

// Définition des outils disponibles
$TOOLS = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'execute_command',
            'description' => 'Run a shell command on the host system.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'The command to execute.']
                ],
                'required' => ['command']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_system_info',
            'description' => 'Discover system details, including available browsers.',
            'parameters' => ['type' => 'object', 'properties' => []]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'update_soul',
            'description' => 'Update persistent memory about the user.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'New information to remember.']
                ],
                'required' => ['content']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'update_personality',
            'description' => 'Update your own personality and internal rules.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Updated personality content.']
                ],
                'required' => ['content']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'update_subconscious',
            'description' => 'Store innovative ideas and lessons learned.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Content for subconscious memory.']
                ],
                'required' => ['content']
            ]
        ]
    ]
];

function getSystemPrompt(): string {
    global $BASE_SYSTEM_PROMPT;
    
    $prompt = getAgentProfile();
    $prompt .= "\n\n" . $BASE_SYSTEM_PROMPT;
    
    $soul = getSoulMemory();
    if ($soul) {
        $prompt .= "\n\n## SOUL (User Memory)\n" . $soul;
    }
    
    $personality = getPersonalityMemory();
    if ($personality) {
        $prompt .= "\n\n## PERSONALITY\n" . $personality;
    }
    
    $subconscious = getSubconsciousMemory();
    if ($subconscious) {
        $prompt .= "\n\n## SUBCONSCIOUS\n" . $subconscious;
    }
    
    return $prompt;
}

class LiteClawAgent {
    private string $model;
    private string $apiKey;
    private ?string $baseUrl;
    private string $provider;
    private string $fullModelName;
    
    public function __construct() {
        global $settings;
        
        $this->model = $settings->LLM_MODEL;
        $this->apiKey = $settings->LLM_API_KEY;
        $this->baseUrl = $settings->LLM_BASE_URL;
        $this->provider = $settings->LLM_PROVIDER;
        
        configureBedrockEnv();
        $this->fullModelName = getFullModelName($this->provider, $this->model, $this->baseUrl);
    }
    
    public function processMessage(string $userMessage, string $sessionId = 'default', string $platform = 'api'): string {
        // Vérifier le break time
        global $settings;
        $now = time();
        
        if ($settings->BREAK_UNTIL > $now) {
            $remaining = intval(($settings->BREAK_UNTIL - $now) / 60);
            if (stripos($userMessage, 'wake up') === false && stripos($userMessage, 'emergency') === false) {
                return "I am currently on a scheduled break for another {$remaining} minutes. Please reach out after that, or say 'wake up' if it's an emergency.";
            }
            $settings->BREAK_UNTIL = 0;
        }
        
        // Construire les messages
        $systemPrompt = getSystemPrompt();
        $history = getSessionHistory($sessionId);
        
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $messages = array_merge($messages, $history);
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        
        // Sauvegarder le message utilisateur
        addMessage($sessionId, ['role' => 'user', 'content' => $userMessage]);
        
        // Appel à l'API LLM
        try {
            $response = callLLM($this->model, $messages, $GLOBALS['TOOLS'] ?? null, false);
            
            if (isset($response['choices'][0]['message'])) {
                $assistantMessage = $response['choices'][0]['message'];
                $content = $assistantMessage['content'] ?? '';
                
                // Sauvegarder la réponse
                addMessage($sessionId, $assistantMessage);
                
                // Gérer les tool calls si présents
                if (!empty($assistantMessage['tool_calls'])) {
                    $content .= $this->executeToolCalls($assistantMessage['tool_calls'], $sessionId, $platform);
                }
                
                return $content;
            }
            
            return "No response from AI.";
        } catch (Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
    
    private function executeToolCalls(array $toolCalls, string $sessionId, string $platform): string {
        $results = [];
        
        foreach ($toolCalls as $tc) {
            $funcName = $tc['function']['name'];
            $args = json_decode($tc['function']['arguments'], true) ?? [];
            
            $output = $this->executeTool($funcName, $args, $sessionId, $platform);
            $results[] = "Executed {$funcName}: {$output}";
            
            // Sauvegarder le résultat du tool call
            addMessage($sessionId, [
                'role' => 'tool',
                'tool_call_id' => $tc['id'],
                'name' => $funcName,
                'content' => $output
            ]);
        }
        
        return "\n\n[Tools executed]: " . implode('; ', $results);
    }
    
    private function executeTool(string $funcName, array $args, string $sessionId, string $platform): string {
        switch ($funcName) {
            case 'execute_command':
                return executeCommand($args['command'] ?? '');
            
            case 'get_system_info':
                return getSystemInfo();
            
            case 'update_soul':
                return updateSoulMemory($args['content'] ?? '');
            
            case 'update_personality':
                return updatePersonalityMemory($args['content'] ?? '');
            
            case 'update_subconscious':
                return updateSubconsciousMemory($args['content'] ?? '');
            
            default:
                return "Unknown tool: {$funcName}";
        }
    }
}

// Instance globale de l'agent
$agent = new LiteClawAgent();

function processMessage(string $message, string $sessionId = 'default', string $platform = 'whatsapp'): string {
    global $agent;
    return $agent->processMessage($message, $sessionId, $platform);
}
