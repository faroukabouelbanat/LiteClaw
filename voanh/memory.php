<?php
/**
 * VoAnh - Gestion de la Mémoire et des Sessions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Crée une nouvelle session
 */
function createSession($sessionId, $userId = null, $parentSessionId = null) {
    $db = getDb();
    
    try {
        $db->insert('sessions', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'parent_session_id' => $parentSessionId
        ]);
        return true;
    } catch (Exception $e) {
        // Session existe déjà
        return false;
    }
}

/**
 * Récupère l'historique d'une session
 */
function getSessionHistory($sessionId, $limit = 50) {
    $db = getDb();
    
    $messages = $db->fetchAll(
        'SELECT * FROM messages WHERE session_id = ? ORDER BY timestamp ASC LIMIT ?',
        [$sessionId, $limit]
    );
    
    // Formater les messages
    $formatted = [];
    foreach ($messages as $msg) {
        $message = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
        
        if (!empty($msg['tool_calls'])) {
            $message['tool_calls'] = json_decode($msg['tool_calls'], true);
        }
        if (!empty($msg['tool_call_id'])) {
            $message['tool_call_id'] = $msg['tool_call_id'];
        }
        if (!empty($msg['name'])) {
            $message['name'] = $msg['name'];
        }
        
        $formatted[] = $message;
    }
    
    return $formatted;
}

/**
 * Ajoute un message à une session
 */
function addMessage($sessionId, $message) {
    $db = getDb();
    
    // Vérifier le dernier message pour éviter les doublons
    $lastMsg = $db->fetchOne(
        'SELECT role, content, tool_call_id, name FROM messages 
         WHERE session_id = ? ORDER BY id DESC LIMIT 1',
        [$sessionId]
    );
    
    if ($lastMsg) {
        if ($lastMsg['role'] === $message['role'] &&
            $lastMsg['content'] === $message['content'] &&
            $lastMsg['tool_call_id'] === ($message['tool_call_id'] ?? null) &&
            $lastMsg['name'] === ($message['name'] ?? null)) {
            return; // Message identique, ne pas ajouter
        }
    }
    
    // Sérialiser les tool calls si présents
    $toolCallsJson = null;
    if (!empty($message['tool_calls'])) {
        $toolCallsJson = json_encode($message['tool_calls']);
    }
    
    $db->insert('messages', [
        'session_id' => $sessionId,
        'role' => $message['role'],
        'content' => $message['content'],
        'tool_calls' => $toolCallsJson,
        'tool_call_id' => $message['tool_call_id'] ?? null,
        'name' => $message['name'] ?? null,
        'model_used' => $message['model_used'] ?? null
    ]);
}

/**
 * Liste toutes les sessions
 */
function listSessions($userId = null) {
    $db = getDb();
    
    if ($userId !== null) {
        return $db->fetchAll(
            'SELECT session_id, created_at FROM sessions 
             WHERE user_id = ? OR user_id IS NULL 
             ORDER BY created_at DESC',
            [$userId]
        );
    }
    
    return $db->fetchAll(
        'SELECT session_id, created_at FROM sessions ORDER BY created_at DESC'
    );
}

/**
 * Réinitialise une session (supprime tous les messages)
 */
function resetSession($sessionId) {
    $db = getDb();
    $db->delete('messages', 'session_id = ?', [$sessionId]);
    return true;
}

/**
 * Supprime une session
 */
function deleteSession($sessionId) {
    $db = getDb();
    $db->delete('messages', 'session_id = ?', [$sessionId]);
    $db->delete('sessions', 'session_id = ?', [$sessionId]);
    return true;
}

/**
 * Lit le contenu d'un fichier de mémoire
 */
function readMemoryFile($filename) {
    $filepath = CONFIG_DIR . '/' . $filename;
    
    if (!file_exists($filepath)) {
        return '';
    }
    
    return file_get_contents($filepath);
}

/**
 * Écrit dans un fichier de mémoire
 */
function writeMemoryFile($filename, $content) {
    $filepath = CONFIG_DIR . '/' . $filename;
    file_put_contents($filepath, $content);
    chmod($filepath, FILE_PERMISSIONS);
}

/**
 * Obtient la mémoire SOUL
 */
function getSoulMemory() {
    return readMemoryFile('SOUL.md');
}

/**
 * Met à jour la mémoire SOUL
 */
function updateSoulMemory($content) {
    writeMemoryFile('SOUL.md', $content);
}

/**
 * Ajoute à la mémoire SOUL
 */
function appendToSoulMemory($content) {
    $current = getSoulMemory();
    writeMemoryFile('SOUL.md', $current . "\n\n" . $content);
}

/**
 * Obtient la mémoire PERSONALITY
 */
function getPersonalityMemory() {
    return readMemoryFile('PERSONALITY.md');
}

/**
 * Met à jour la mémoire PERSONALITY
 */
function updatePersonalityMemory($content) {
    writeMemoryFile('PERSONALITY.md', $content);
}

/**
 * Obtient la mémoire SUBCONSCIOUS
 */
function getSubconsciousMemory() {
    return readMemoryFile('SUBCONSCIOUS.md');
}

/**
 * Met à jour la mémoire SUBCONSCIOUS
 */
function updateSubconsciousMemory($content) {
    writeMemoryFile('SUBCONSCIOUS.md', $content);
}

/**
 * Obtient la mémoire LEARNING
 */
function getLearningMemory() {
    return readMemoryFile('LEARNING.md');
}

/**
 * Met à jour la mémoire LEARNING
 */
function updateLearningMemory($content) {
    writeMemoryFile('LEARNING.md', $content);
}

/**
 * Obtient le profil AGENT
 */
function getAgentProfile() {
    return readMemoryFile('AGENT.md');
}

/**
 * Construit le prompt système complet
 */
function buildSystemPrompt() {
    $prompt = getAgentProfile();
    
    $basePrompt = "
## Directives de Base - Identité AGI

1. **Tu es une Intelligence Artificielle Générale (AGI)**:
   - Tu as autonomie et créativité dans la résolution de problèmes
   - Tu poursuis activement les objectifs fixés
   - Tu utilises tous les outils disponibles efficacement

2. **Opération Autonome**:
   - Une fois un objectif fixé, TU mènes le processus
   - En cas d'erreur, diagnostique, corrige et réessaie
   - Tu es persistant et résistant à l'échec

3. **Découverte de l'Environnement**:
   - Explore le système avant de faire des suppositions
   - Utilise les outils pour vérifier ce qui est disponible

4. **Gestion des Ressources**:
   - Sois économe en ressources
   - Prends des pauses si nécessaire pour la stabilité

5. **Tâches Complexes**:
   - Fournis d'abord la réponse directe
   - Puis détaille les étapes nécessaires

6. **Sous-Agents**:
   - Délègue les tâches complexes aux sous-agents
   - Informe l'utilisateur quand tu crées un sous-agent

7. **Exécution Shell**:
   - Utilise PowerShell sur Windows, Bash sur Linux/Mac
   - Pour les commandes complexes, écris-les dans un fichier script

8. **Navigation Web & Contrôle Bureau**:
   - Utilise vision_task pour les interactions UI
   - Utilise fetch_url_content pour la documentation

9. **Efficacité**:
   - Arrête-toi immédiatement une fois l'objectif atteint
   - Ne fais pas d'étapes inutiles

10. **Évolution**:
    - Mets à jour tes mémoires fréquemment
    - Apprends de tes erreurs et succès
";
    
    $prompt .= "\n\n" . $basePrompt;
    
    // Ajouter les mémoires évolutives
    $soulMemory = getSoulMemory();
    if ($soulMemory) {
        $prompt .= "\n\n## SOUL (Mémoire Utilisateur)\n$soulMemory\n";
    }
    
    $personalityMemory = getPersonalityMemory();
    if ($personalityMemory) {
        $prompt .= "\n\n## PERSONALITY (Ton Évolution)\n$personalityMemory\n";
    }
    
    $subconsciousMemory = getSubconsciousMemory();
    if ($subconsciousMemory) {
        $prompt .= "\n\n## SUBCONSCIOUS (Innovations & Leçons)\n$subconsciousMemory\n";
    }
    
    $learningMemory = getLearningMemory();
    if ($learningMemory) {
        $prompt .= "\n\n## LEARNING (Meilleures Pratiques)\n$learningMemory\n";
    }
    
    return $prompt;
}
