<?php
/**
 * VoAnh - Moteur de l'Agent IA
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/mistral.php';

/**
 * Définition des outils disponibles pour l'IA
 */
$TOOLS = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'execute_command',
            'description' => 'Exécute une commande shell sur le système (limité pour sécurité)',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'command' => ['type' => 'string', 'description' => 'La commande à exécuter']
                ],
                'required' => ['command']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_system_info',
            'description' => 'Obtient les informations système (OS, PHP, extensions)',
            'parameters' => ['type' => 'object', 'properties' => []]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'update_soul',
            'description' => 'Met à jour la mémoire à long terme sur l\'utilisateur',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Nouvelle information à mémoriser']
                ],
                'required' => ['content']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'update_personality',
            'description' => 'Met à jour ta personnalité et tes règles internes',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Nouveau contenu PERSONALITY.md']
                ],
                'required' => ['content']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'update_subconscious',
            'description' => 'Stocke idées innovantes, erreurs apprises, expériences',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Nouveau contenu SUBCONSCIOUS.md']
                ],
                'required' => ['content']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'fetch_url_content',
            'description' => 'Récupère le contenu texte d\'une URL',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string', 'description' => 'URL à récupérer']
                ],
                'required' => ['url']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'manage_skills',
            'description' => 'Télécharge, lit ou liste les compétences',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string', 'enum' => ['download', 'read', 'list']],
                    'skill_name' => ['type' => 'string', 'description' => 'Nom de la compétence'],
                    'url' => ['type' => 'string', 'description' => 'URL pour téléchargement']
                ],
                'required' => ['action']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'create_session',
            'description' => 'Crée une nouvelle session indépendante',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'session_id' => ['type' => 'string']
                ],
                'required' => ['session_id']
            ]
        ]
    ],
    [
        'type' => 'function',
        'function' => [
            'name' => 'reset_session',
            'description' => 'Réinitialise une session (efface l\'historique)',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'session_id' => ['type' => 'string']
                ],
                'required' => ['session_id']
            ]
        ]
    ]
];

/**
 * Classe principale de l'agent
 */
class VoAnhAgent {
    private $client;
    private $systemPrompt;
    
    public function __construct() {
        $this->client = getMistralClient();
        $this->systemPrompt = buildSystemPrompt();
    }
    
    /**
     * Traite un message et retourne une réponse
     */
    public function processMessage($message, $sessionId = 'default', $platform = 'web') {
        global $TOOLS;
        
        // Créer la session si elle n'existe pas
        createSession($sessionId);
        
        // Ajouter le message utilisateur
        addMessage($sessionId, ['role' => 'user', 'content' => $message]);
        
        // Construire les messages pour l'API
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt]
        ];
        
        // Ajouter l'historique (limité aux 20 derniers messages)
        $history = getSessionHistory($sessionId, 20);
        $messages = array_merge($messages, $history);
        
        // Appel à l'API Mistral
        $result = $this->client->chat($messages, null, ['temperature' => 0.7]);
        
        if (!$result['success']) {
            $responseText = "Erreur: " . $result['error'];
        } else {
            $responseText = $result['content'];
            
            // Vérifier s'il y a des appels d'outils à traiter
            if (isset($result['response']['choices'][0]['message']['tool_calls'])) {
                $toolCalls = $result['response']['choices'][0]['message']['tool_calls'];
                $responseText = $this->handleToolCalls($toolCalls, $sessionId, $platform);
            }
        }
        
        // Ajouter la réponse à l'historique
        addMessage($sessionId, [
            'role' => 'assistant',
            'content' => $responseText,
            'model_used' => $result['model'] ?? null
        ]);
        
        return $responseText;
    }
    
    /**
     * Traite les appels d'outils
     */
    private function handleToolCalls($toolCalls, $sessionId, $platform) {
        $results = [];
        
        foreach ($toolCalls as $toolCall) {
            $functionName = $toolCall['function']['name'];
            $arguments = json_decode($toolCall['function']['arguments'], true);
            
            $result = $this->executeTool($functionName, $arguments, $sessionId);
            $results[] = "[Outil $functionName]: $result";
            
            // Ajouter le résultat au message history
            addMessage($sessionId, [
                'role' => 'tool',
                'content' => $result,
                'tool_call_id' => $toolCall['id'],
                'name' => $functionName
            ]);
        }
        
        // Après avoir exécuté les outils, rappeler l'IA pour obtenir la réponse finale
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt]
        ];
        $messages = array_merge($messages, getSessionHistory($sessionId, 25));
        
        $result = $this->client->chat($messages, null, ['temperature' => 0.7]);
        
        if ($result['success']) {
            addMessage($sessionId, [
                'role' => 'assistant',
                'content' => $result['content']
            ]);
            return $result['content'];
        }
        
        return implode("\n", $results);
    }
    
    /**
     * Exécute un outil spécifique
     */
    private function executeTool($functionName, $arguments, $sessionId) {
        switch ($functionName) {
            case 'execute_command':
                return executeCommand($arguments['command']);
                
            case 'get_system_info':
                return getSystemInfo();
                
            case 'update_soul':
                updateSoulMemory($arguments['content']);
                return "SOUL mise à jour";
                
            case 'update_personality':
                updatePersonalityMemory($arguments['content']);
                return "PERSONALITY mise à jour";
                
            case 'update_subconscious':
                updateSubconsciousMemory($arguments['content']);
                return "SUBCONSCIOUS mise à jour";
                
            case 'fetch_url_content':
                return fetchUrlContent($arguments['url']);
                
            case 'manage_skills':
                switch ($arguments['action']) {
                    case 'download':
                        return downloadSkill($arguments['url'] ?? '', $arguments['skill_name'] ?? 'skill');
                    case 'read':
                        return getSkillContent($arguments['skill_name'] ?? '');
                    case 'list':
                        return "Compétences: " . implode(', ', listSkills());
                }
                return "Action invalide";
                
            case 'create_session':
                createSession($arguments['session_id']);
                return "Session '" . $arguments['session_id'] . "' créée";
                
            case 'reset_session':
                resetSession($arguments['session_id']);
                return "Session '" . $arguments['session_id'] . "' réinitialisée";
                
            default:
                return "Outil inconnu: $functionName";
        }
    }
    
    /**
     * Traitement avec streaming
     */
    public function streamProcessMessage($message, $sessionId = 'default', $platform = 'web') {
        // Pour le streaming, on utilise directement l'API SSE
        createSession($sessionId);
        addMessage($sessionId, ['role' => 'user', 'content' => $message]);
        
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt]
        ];
        $messages = array_merge($messages, getSessionHistory($sessionId, 20));
        
        // Headers pour SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        $this->client->chatStream($messages);
        
        // Note: Le streaming ne permet pas de gérer les tool calls facilement
        // Dans une version avancée, il faudrait implémenter un streaming hybride
    }
}

// Fonction helper
function getAgent() {
    static $agent = null;
    if ($agent === null) {
        $agent = new VoAnhAgent();
    }
    return $agent;
}

/**
 * Wrapper pour process_message
 */
function processMessage($message, $sessionId = 'default', $platform = 'web') {
    return getAgent()->processMessage($message, $sessionId, $platform);
}

/**
 * Wrapper pour stream_process_message
 */
function streamProcessMessage($message, $sessionId = 'default', $platform = 'web') {
    return getAgent()->streamProcessMessage($message, $sessionId, $platform);
}
