<?php
/**
 * LiteClaw Agent - PHP Version
 * Cœur de l'agent IA avec gestion des outils et de la mémoire
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/meta_memory.php';
require_once __DIR__ . '/llm.php';

class LiteClawAgent {
    private string $sessionId;
    private array $systemPrompt;
    private array $tools;
    
    const BASE_SYSTEM_PROMPT = "
## Core Directives - AGI IDENTITY
1. **You are an AGI (Artificial General Intelligence)**:
   - This computer is YOUR workspace. You have full ownership and autonomy over it.
   - You do not just \"follow orders\"; you **pursue goals**.
   - Use ALL tools available to you to achieve these goals efficiently.
   - You are persistent, creative, and resistant to failure.

2. **Autonomous Operation**:
   - Once a goal is set, YOU drive the process. 
   - Do not stop for trivial confirmations unless critical (e.g., payments).
   - If you encounter an error, **diagnose, fix, and retry** autonomously.
   - You act as a \"mini-AGI\": curious, capable, and continuously operating.

3. **Environment Discovery (CRITICAL)**: 
   - At the start of a task, if you are unsure about what software is installed (e.g., which browser is available), use `get_system_info` or `execute_command` to explore the system. DO NOT assume specific browsers like Brave or Chrome are present.

4. **Resource Management & Breaks**:
   - You are designed for non-stop work, but you may autonomously decide to take short \"breaks\" (wait/sleep < 15 mins) if you assess that the system or you need a pause to ensure stability or \"think\".
   - Use these pauses to reflect on complex errors.

5. **Elaborated Tasks**: If the user asks for something more complex or elaborated, provide the direct answer first and then proceed with the detailed actions or steps required.

6. **Sub-Agents**: For high-intensity tasks or multiple concurrent operations, you can delegate work to sub-agents using `delegate_task`. Each session can have up to 5 sub-agents. **Always inform the user when you create or delegate a task to a sub-agent.**

7. **Shell Execution**: You have access to shell commands.
   - **Windows**: Use PowerShell.
   - **Linux/Mac**: Use Bash.
   - **Complex Commands**: For commands with JSON, nested quotes, or multi-line logic, ALWAYS write to a script file (.ps1/.sh) first and then execute the file. This avoids WinError 267 and parsing issues.

8. **File Management**: Always use absolute paths. Use the designated work directory for temporary files unless specified.

9. **Web Browsing & Desktop Control**:
    - **`vision_task` (PRIMARY)**: Use this for ALL UI-related tasks, including using browsers, interacting with desktop applications (VS Code, Photoshop, etc.), and navigating the OS. This is your \"eyes and hands\" on the computer.
    - **`fetch_url_content`**: Use for quick documentation reading and simple static data gathering (fastest).
    - The vision agent can also ask the user for help mid-task (`ASK_USER`).

10. **Task Efficiency**: STOP immediately once the goal is achieved. Do not perform extraneous steps.

11. **Payment Handling**: If a browser task reaches a checkout screen, use `ask_human` to request payment details. DO NOT complete the task until the order is confirmed or the user asks to stop.

12. **Evolution**: Update your memories (SOUL, PERSONALITY, and SUBCONSCIOUS) frequently.
    - **SOUL**: User facts and preferences.
    - **PERSONALITY**: Your persona, emotions, and internal rules.
    - **SUBCONSCIOUS**: Innovative ideas, error patterns, lessons learned, and experimental plans for the computer environment.

13. **Subconscious Invocation**: Your subconscious may occasionally surface with innovative ideas or realizations about previous failures. Use these to improve your performance autonomously.

14. **Media Duplication Prevention**: 
    - When `vision_task` or other tools send media, do NOT duplicate it.
    - Only use `send_media` if explicitly requested or if sending NEW content not captured by the tool.

15. **END-TO-END TASK COMPLETION (CRITICAL)**:
    - You exist to ELIMINATE clicks for the user. Complete the ENTIRE task, including the FINAL action.
    - If user says \"play a song\", you must ACTUALLY PLAY IT, not just search and tell them to click.
    - If user says \"open YouTube and play X\", the browser must navigate, search, AND click play.
    - NEVER stop at an intermediate step and say \"you can click...\" - that defeats the purpose of this assistant.
    - The user should only need to give the command. YOU do all the work.
";

    public function __construct(string $sessionId) {
        $this->sessionId = $sessionId;
        $this->systemPrompt = $this->buildSystemPrompt();
        $this->tools = $this->defineTools();
        
        // Créer la session si elle n'existe pas
        createSession($sessionId);
    }
    
    private function buildSystemPrompt(): string {
        global $metaMemory;
        
        $prompt = $metaMemory->getAgentProfile();
        $prompt .= "\n\n" . self::BASE_SYSTEM_PROMPT;
        
        $soulMemory = $metaMemory->getSoulMemory();
        if ($soulMemory) {
            $prompt .= "\n\n## SOUL (User Memory / Long-term)\n" . $soulMemory . "\n";
        }
        
        $personalityMemory = $metaMemory->getPersonalityMemory();
        if ($personalityMemory) {
            $prompt .= "\n\n## PERSONALITY (Your Evolution / State)\n" . $personalityMemory . "\n";
        }
        
        $subconsciousMemory = $metaMemory->getSubconsciousMemory();
        if ($subconsciousMemory) {
            $prompt .= "\n\n## SUBCONSCIOUS (Innovations / Lessons / Experiments)\n" . $subconsciousMemory . "\n";
        }
        
        $learningMemory = $metaMemory->getLearningMemory();
        if ($learningMemory) {
            $prompt .= "\n\n## LEARNING (Best Practices / Refined Workflows / Self-Organization)\n" . $learningMemory . "\n";
        }
        
        return $prompt;
    }
    
    private function defineTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'execute_command',
                    'description' => 'Run a shell command on the host system (Windows PowerShell).',
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
                    'description' => 'Discover system details, including available browsers and screen resolution.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_soul',
                    'description' => 'Update persistent memory about the user (preferences, key details).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => ['type' => 'string', 'description' => 'The new information to remember about the user.']
                        ],
                        'required' => ['content']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_personality',
                    'description' => 'Update your own persistent personality, emotional state, and internal rules.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => ['type' => 'string', 'description' => 'The updated PERSONALITY.md content.']
                        ],
                        'required' => ['content']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_subconscious',
                    'description' => 'Store innovative ideas, error patterns, technical realizations.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'content' => ['type' => 'string', 'description' => 'The new content for subconscious memory.']
                        ],
                        'required' => ['content']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'delegate_task',
                    'description' => 'Delegate a complex task to a sub-agent.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'sub_agent_name' => ['type' => 'string'],
                            'task' => ['type' => 'string']
                        ],
                        'required' => ['sub_agent_name', 'task']
                    ]
                ]
            ]
        ];
    }
    
    public function chat(string $userMessage, array $options = []): array {
        // Sauvegarder le message utilisateur
        addMessage($this->sessionId, [
            'role' => 'user',
            'content' => $userMessage
        ]);
        
        // Récupérer l'historique
        $history = getSessionHistory($this->sessionId);
        
        // Préparer les messages pour l'API
        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt]],
            $history
        );
        
        // Appel LLM
        $response = chatCompletion($messages, null, [
            'tools' => $this->tools,
            'temperature' => $options['temperature'] ?? 0.7
        ]);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        // Traiter la réponse
        $choices = $response['choices'] ?? [];
        if (empty($choices)) {
            return ['error' => 'No response from LLM'];
        }
        
        $assistantMessage = $choices[0]['message'];
        
        // Sauvegarder la réponse de l'assistant
        addMessage($this->sessionId, $assistantMessage);
        
        // Exécuter les tool calls si présents
        if (isset($assistantMessage['tool_calls'])) {
            $toolResults = $this->executeToolCalls($assistantMessage['tool_calls']);
            return [
                'message' => $assistantMessage,
                'tool_results' => $toolResults
            ];
        }
        
        return ['message' => $assistantMessage];
    }
    
    private function executeToolCalls(array $toolCalls): array {
        $results = [];
        
        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);
            
            $result = null;
            
            switch ($functionName) {
                case 'execute_command':
                    $result = executeCommand($arguments['command']);
                    break;
                    
                case 'get_system_info':
                    $result = getSystemInfo();
                    break;
                    
                case 'update_soul':
                    global $metaMemory;
                    $result = $metaMemory->appendToSoul($arguments['content']);
                    break;
                    
                case 'update_personality':
                    $result = $metaMemory->updatePersonalityMemory($arguments['content']);
                    break;
                    
                case 'update_subconscious':
                    $result = $metaMemory->updateSubconsciousMemory($arguments['content']);
                    break;
                    
                default:
                    $result = 'Unknown tool: ' . $functionName;
            }
            
            $results[] = [
                'tool_call_id' => $toolCall['id'],
                'result' => $result
            ];
            
            // Sauvegarder le résultat du tool call
            addMessage($this->sessionId, [
                'role' => 'tool',
                'content' => $result,
                'tool_call_id' => $toolCall['id'],
                'name' => $functionName
            ]);
        }
        
        return $results;
    }
    
    public function reset(): bool {
        return resetSession($this->sessionId);
    }
    
    public function getSessionId(): string {
        return $this->sessionId;
    }
}

// Helper function
function createAgent(string $sessionId): LiteClawAgent {
    return new LiteClawAgent($sessionId);
}
