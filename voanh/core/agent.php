<?php
/**
 * VoAnh - Système d'Agents Autonomes et Sous-Agents
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mistral.php';
require_once __DIR__ . '/memory.php';

class AgentSystem {
    private $db;
    private $mistral;
    private $memory;
    
    public function __construct($userId = null, $userApiKey = null) {
        $this->db = Database::getInstance();
        $this->mistral = getMistralClient($userApiKey);
        $this->memory = new MemorySystem();
        $this->userId = $userId;
    }
    
    // === GESTION DES AGENTS ===
    
    public function createAgent($name, $type, $modelId, $capabilities = []) {
        if (!$this->userId) {
            return ['success' => false, 'error' => 'Utilisateur non authentifié'];
        }
        
        $agentId = $this->db->insert('agents', [
            ':user_id' => $this->userId,
            ':name' => $name,
            ':type' => $type,
            ':model_id' => $modelId,
            ':capabilities' => json_encode($capabilities),
            ':status' => 'idle'
        ]);
        
        voanh_log("Agent created: $name (ID: $agentId)", 3);
        return ['success' => true, 'agent_id' => $agentId];
    }
    
    public function getAgent($agentId) {
        return $this->db->fetch("SELECT * FROM agents WHERE id = :id", [':id' => $agentId]);
    }
    
    public function getUserAgents() {
        return $this->db->fetchAll("SELECT * FROM agents WHERE user_id = :user_id ORDER BY created_at DESC", [':user_id' => $this->userId]);
    }
    
    public function updateAgentStatus($agentId, $status, $currentTask = null) {
        $data = [':status' => $status, ':last_active' => date('Y-m-d H:i:s')];
        if ($currentTask) {
            $data[':current_task'] = $currentTask;
        }
        $this->db->update('agents', $data, 'id = :id', [':id' => $agentId]);
    }
    
    // === SOUS-AGENTS ===
    
    public function createSubAgent($parentAgentId, $name, $specialization) {
        $subAgentId = $this->db->insert('subagents', [
            ':parent_agent_id' => $parentAgentId,
            ':name' => $name,
            ':specialization' => $specialization
        ]);
        
        voanh_log("Sub-agent created: $name for agent $parentAgentId", 3);
        return ['success' => true, 'subagent_id' => $subAgentId];
    }
    
    public function assignTaskToSubAgent($subAgentId, $task) {
        $this->db->update('subagents', [
            ':assigned_task' => $task,
            ':status' => 'working'
        ], 'id = :id', [':id' => $subAgentId]);
    }
    
    public function completeSubAgentTask($subAgentId) {
        $subAgent = $this->db->fetch("SELECT * FROM subagents WHERE id = :id", [':id' => $subAgentId]);
        $this->db->update('subagents', [
            ':status' => 'idle',
            ':assigned_task' => null,
            ':completed_tasks' => $subAgent['completed_tasks'] + 1
        ], 'id = :id', [':id' => $subAgentId]);
    }
    
    // === ORCHESTRATION MULTI-AGENTS ===
    
    public function orchestrateTask($taskDescription, $options = []) {
        if (!$this->userId) {
            return ['success' => false, 'error' => 'Utilisateur non authentifié'];
        }
        
        // Créer une tâche principale
        $taskId = $this->db->insert('tasks', [
            ':user_id' => $this->userId,
            ':title' => substr($taskDescription, 0, 100),
            ':description' => $taskDescription,
            ':status' => 'planning',
            ':priority' => $options['priority'] ?? 5
        ]);
        
        // Utiliser l'agent routeur pour décomposer la tâche
        $planResult = $this->generatePlan($taskDescription, $options);
        
        if (!$planResult['success']) {
            return $planResult;
        }
        
        $plan = $planResult['plan'];
        $steps = $planResult['steps'];
        
        // Mettre à jour la tâche avec le plan
        $this->db->update('tasks', [
            ':plan' => json_encode($plan),
            ':steps' => json_encode($steps),
            ':status' => 'pending'
        ], 'id = :id', [':id' => $taskId]);
        
        // Assigner des sous-agents si nécessaire
        if ($options['use_subagents'] ?? true) {
            $this->assignSubAgentsToSteps($taskId, $steps);
        }
        
        return ['success' => true, 'task_id' => $taskId, 'plan' => $plan, 'steps' => $steps];
    }
    
    private function generatePlan($taskDescription, $options = []) {
        $model = $options['model'] ?? PLANNER_AGENT_MODEL;
        
        $systemPrompt = "Tu es un planificateur expert. Décompose la tâche en étapes claires et exécutables.
Pour chaque étape, précise:
1. L'action à effectuer
2. Le type d'agent requis (code, vision, research, creative, etc.)
3. Les dépendances avec d'autres étapes
4. Le résultat attendu

Format de réponse JSON:
{
    \"plan\": \"Description globale du plan\",
    \"steps\": [
        {\"order\": 1, \"action\": \"...\", \"agent_type\": \"...\", \"dependencies\": [], \"expected_output\": \"...\"}
    ]
}";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $taskDescription]
        ];
        
        $result = $this->mistral->chat($messages, $model, ['temperature' => 0.3]);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Parser la réponse JSON
        $content = $result['content'];
        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}') + 1;
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonStr = substr($content, $jsonStart, $jsonEnd - $jsonStart);
            $parsed = json_decode($jsonStr, true);
            
            if ($parsed) {
                return ['success' => true, 'plan' => $parsed['plan'] ?? '', 'steps' => $parsed['steps'] ?? []];
            }
        }
        
        return ['success' => false, 'error' => 'Impossible de parser le plan'];
    }
    
    private function assignSubAgentsToSteps($taskId, $steps) {
        $agentTypes = [
            'code' => CODE_AGENT_MODEL,
            'vision' => VISION_AGENT_MODEL,
            'creative' => CREATIVE_AGENT_MODEL,
            'research' => MASTER_AGENT_MODEL
        ];
        
        foreach ($steps as $index => $step) {
            $agentType = strtolower($step['agent_type'] ?? 'general');
            $modelId = $agentTypes[$agentType] ?? MASTER_AGENT_MODEL;
            
            // Créer ou réutiliser un agent pour ce type
            $existingAgent = $this->db->fetch(
                "SELECT * FROM agents WHERE user_id = :user_id AND type = :type LIMIT 1",
                [':user_id' => $this->userId, ':type' => $agentType]
            );
            
            if (!$existingAgent) {
                $agentResult = $this->createAgent("Agent $agentType", $agentType, $modelId);
                $agentId = $agentResult['agent_id'] ?? null;
            } else {
                $agentId = $existingAgent['id'];
            }
            
            if ($agentId) {
                // Créer un sous-agent pour cette étape
                $subAgentResult = $this->createSubAgent($agentId, "SubAgent_Step{$index}", $step['action']);
                
                if ($subAgentResult['success']) {
                    $this->assignTaskToSubAgent($subAgentResult['subagent_id'], json_encode($step));
                }
            }
        }
    }
    
    // === EXÉCUTION DE TÂCHE ===
    
    public function executeTask($taskId) {
        $task = $this->db->fetch("SELECT * FROM tasks WHERE id = :id", [':id' => $taskId]);
        
        if (!$task) {
            return ['success' => false, 'error' => 'Tâche non trouvée'];
        }
        
        $this->db->update('tasks', [':status' => 'running', ':started_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $taskId]);
        
        $steps = json_decode($task['steps'] ?? '[]', true);
        $currentStep = $task['current_step'] ?? 0;
        
        if ($currentStep >= count($steps)) {
            $this->db->update('tasks', [':status' => 'completed', ':completed_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $taskId]);
            return ['success' => true, 'result' => $task['result'], 'completed' => true];
        }
        
        $step = $steps[$currentStep];
        $agentType = strtolower($step['agent_type'] ?? 'general');
        
        // Exécuter l'étape avec l'agent approprié
        $executionResult = $this->executeStep($step, $task);
        
        if ($executionResult['success']) {
            $this->db->update('tasks', [
                ':current_step' => $currentStep + 1
            ], 'id = :id', [':id' => $taskId]);
            
            // Mettre à jour la mémoire d'apprentissage
            if (AUTO_LEARNING_ENABLED) {
                $this->memory->addToLearningMemory($this->userId, 'task_execution', [
                    'task_id' => $taskId,
                    'step' => $step,
                    'result' => $executionResult['result'],
                    'success' => true
                ], 1.0);
            }
        } else {
            $this->db->update('tasks', [
                ':status' => 'failed',
                ':error_message' => $executionResult['error']
            ], 'id = :id', [':id' => $taskId]);
        }
        
        return $executionResult;
    }
    
    private function executeStep($step, $task) {
        $agentType = strtolower($step['agent_type'] ?? 'general');
        $modelId = $this->getModelForAgentType($agentType);
        
        $systemPrompt = "Tu es un agent spécialisé en {$agentType}. Exécute la tâche suivante avec précision.";
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $step['action']]
        ];
        
        // Ajouter le contexte de la tâche
        if ($task['description']) {
            $messages[] = ['role' => 'context', 'content' => "Contexte: " . $task['description']];
        }
        
        $result = $this->mistral->chat($messages, $modelId);
        
        if ($result['success']) {
            return ['success' => true, 'result' => $result['content'], 'step_completed' => $step];
        }
        
        return $result;
    }
    
    private function getModelForAgentType($agentType) {
        $models = [
            'code' => CODE_AGENT_MODEL,
            'vision' => VISION_AGENT_MODEL,
            'creative' => CREATIVE_AGENT_MODEL,
            'planner' => PLANNER_AGENT_MODEL,
            'general' => MASTER_AGENT_MODEL
        ];
        
        return $models[$agentType] ?? MASTER_AGENT_MODEL;
    }
    
    // === AGENT DE VISION ===
    
    public function analyzeImage($imageData, $prompt = "Décris cette image en détail") {
        $model = VISION_AGENT_MODEL;
        
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageData]]
                ]
            ]
        ];
        
        return $this->mistral->chat($messages, $model);
    }
}
