<?php
/**
 * VoAnh - Système de Mémoire Évolutif (SOUL, PERSONALITY, SUBCONSCIOUS, LEARNING)
 */

require_once __DIR__ . '/database.php';

class MemorySystem {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // === SOUL MEMORY (Identité fondamentale) ===
    
    public function getSoulMemory($userId) {
        $memory = $this->db->fetch("SELECT * FROM soul_memory WHERE user_id = :user_id", [':user_id' => $userId]);
        if (!$memory) return null;
        
        return [
            'identity_profile' => json_decode($memory['identity_profile'] ?? '{}', true),
            'core_values' => json_decode($memory['core_values'] ?? '[]', true),
            'personality_traits' => json_decode($memory['personality_traits'] ?? '[]', true),
            'updated_at' => $memory['updated_at']
        ];
    }
    
    public function updateSoulMemory($userId, $data) {
        $current = $this->getSoulMemory($userId);
        
        $identityProfile = array_merge($current['identity_profile'] ?? [], $data['identity_profile'] ?? []);
        $coreValues = array_unique(array_merge($current['core_values'] ?? [], $data['core_values'] ?? []));
        $personalityTraits = array_unique(array_merge($current['personality_traits'] ?? [], $data['personality_traits'] ?? []));
        
        $this->db->update('soul_memory', [
            ':identity_profile' => json_encode($identityProfile),
            ':core_values' => json_encode($coreValues),
            ':personality_traits' => json_encode($personalityTraits),
            ':updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = :user_id', [':user_id' => $userId]);
        
        voanh_log("Soul memory updated for user $userId", 3);
        return $this->getSoulMemory($userId);
    }
    
    // === PERSONALITY MEMORY (Préférences comportementales) ===
    
    public function getPersonalityMemory($userId) {
        $memory = $this->db->fetch("SELECT * FROM personality_memory WHERE user_id = :user_id", [':user_id' => $userId]);
        if (!$memory) return null;
        
        return [
            'communication_style' => json_decode($memory['communication_style'] ?? '{}', true),
            'response_preferences' => json_decode($memory['response_preferences'] ?? '{}', true),
            'tone_settings' => json_decode($memory['tone_settings'] ?? '{}', true),
            'updated_at' => $memory['updated_at']
        ];
    }
    
    public function updatePersonalityMemory($userId, $data) {
        $current = $this->getPersonalityMemory($userId);
        
        $commStyle = array_merge_recursive($current['communication_style'] ?? [], $data['communication_style'] ?? []);
        $respPrefs = array_merge_recursive($current['response_preferences'] ?? [], $data['response_preferences'] ?? []);
        $toneSettings = array_merge_recursive($current['tone_settings'] ?? [], $data['tone_settings'] ?? []);
        
        $this->db->update('personality_memory', [
            ':communication_style' => json_encode($commStyle),
            ':response_preferences' => json_encode($respPrefs),
            ':tone_settings' => json_encode($toneSettings),
            ':updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = :user_id', [':user_id' => $userId]);
        
        voanh_log("Personality memory updated for user $userId", 3);
        return $this->getPersonalityMemory($userId);
    }
    
    // === SUBCONSCIOUS MEMORY (Patterns implicites) ===
    
    public function getSubconsciousMemory($userId) {
        $memory = $this->db->fetch("SELECT * FROM subconscious_memory WHERE user_id = :user_id", [':user_id' => $userId]);
        if (!$memory) return null;
        
        return [
            'implicit_patterns' => json_decode($memory['implicit_patterns'] ?? '{}', true),
            'behavioral_tendencies' => json_decode($memory['behavioral_tendencies'] ?? '{}', true),
            'learned_associations' => json_decode($memory['learned_associations'] ?? '{}', true),
            'confidence_scores' => json_decode($memory['confidence_scores'] ?? '{}', true),
            'updated_at' => $memory['updated_at']
        ];
    }
    
    public function updateSubconsciousMemory($userId, $pattern, $confidence = 0.5) {
        $current = $this->getSubconsciousMemory($userId);
        
        $patterns = $current['implicit_patterns'] ?? [];
        $tendencies = $current['behavioral_tendencies'] ?? [];
        $associations = $current['learned_associations'] ?? [];
        $scores = $current['confidence_scores'] ?? [];
        
        // Mise à jour des patterns avec seuil de confiance
        if ($confidence >= LEARNING_THRESHOLD) {
            $patterns[$pattern['type']][] = $pattern['value'];
            $scores[$pattern['type'] . '_' . md5($pattern['value'])] = $confidence;
        }
        
        $this->db->update('subconscious_memory', [
            ':implicit_patterns' => json_encode($patterns),
            ':behavioral_tendencies' => json_encode($tendencies),
            ':learned_associations' => json_encode($associations),
            ':confidence_scores' => json_encode($scores),
            ':updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = :user_id', [':user_id' => $userId]);
        
        voanh_log("Subconscious pattern updated for user $userId: " . $pattern['type'], 4);
        return $this->getSubconsciousMemory($userId);
    }
    
    // === LEARNING MEMORY (Connaissances acquises) ===
    
    public function getLearningMemory($userId) {
        $memory = $this->db->fetch("SELECT * FROM learning_memory WHERE user_id = :user_id", [':user_id' => $userId]);
        if (!$memory) return null;
        
        return [
            'knowledge_base' => json_decode($memory['knowledge_base'] ?? '{}', true),
            'skills_acquired' => json_decode($memory['skills_acquired'] ?? '[]', true),
            'corrections_applied' => json_decode($memory['corrections_applied'] ?? '[]', true),
            'success_patterns' => json_decode($memory['success_patterns'] ?? '[]', true),
            'updated_at' => $memory['updated_at']
        ];
    }
    
    public function addToLearningMemory($userId, $knowledgeType, $knowledgeData, $successScore = 1.0) {
        $current = $this->getLearningMemory($userId);
        
        $knowledgeBase = $current['knowledge_base'] ?? [];
        $skills = $current['skills_acquired'] ?? [];
        $corrections = $current['corrections_applied'] ?? [];
        $successPatterns = $current['success_patterns'] ?? [];
        
        // Ajouter la connaissance
        if (!isset($knowledgeBase[$knowledgeType])) {
            $knowledgeBase[$knowledgeType] = [];
        }
        $knowledgeBase[$knowledgeType][] = [
            'data' => $knowledgeData,
            'timestamp' => date('Y-m-d H:i:s'),
            'confidence' => $successScore
        ];
        
        // Mettre à jour les patterns de succès
        if ($successScore >= LEARNING_THRESHOLD) {
            $successPatterns[] = [
                'type' => $knowledgeType,
                'score' => $successScore,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        $this->db->update('learning_memory', [
            ':knowledge_base' => json_encode($knowledgeBase),
            ':skills_acquired' => json_encode($skills),
            ':corrections_applied' => json_encode($corrections),
            ':success_patterns' => json_encode($successPatterns),
            ':updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = :user_id', [':user_id' => $userId]);
        
        voanh_log("Knowledge added to learning memory for user $userId: $knowledgeType", 3);
        return $this->getLearningMemory($userId);
    }
    
    public function applyCorrection($userId, $correctionData) {
        $current = $this->getLearningMemory($userId);
        $corrections = $current['corrections_applied'] ?? [];
        
        $corrections[] = [
            'correction' => $correctionData,
            'timestamp' => date('Y-m-d H:i:s'),
            'applied' => true
        ];
        
        $this->db->update('learning_memory', [
            ':corrections_applied' => json_encode($corrections),
            ':updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = :user_id', [':user_id' => $userId]);
        
        voanh_log("Correction applied for user $userId", 3);
    }
    
    // === CONSOLIDATION MÉMOIRE ===
    
    public function consolidateMemories($userId) {
        // Analyser les patterns et consolider les mémoires
        $soul = $this->getSoulMemory($userId);
        $personality = $this->getPersonalityMemory($userId);
        $subconscious = $this->getSubconsciousMemory($userId);
        $learning = $this->getLearningMemory($userId);
        
        // Extraire les insights des différentes mémoires
        $insights = [
            'dominant_traits' => array_slice($soul['personality_traits'] ?? [], 0, 5),
            'preferred_communication' => $personality['communication_style'] ?? [],
            'high_confidence_patterns' => [],
            'recent_skills' => array_slice($learning['skills_acquired'] ?? [], -10)
        ];
        
        // Identifier les patterns haute confiance
        $scores = $subconscious['confidence_scores'] ?? [];
        foreach ($scores as $key => $score) {
            if ($score >= LEARNING_THRESHOLD) {
                $insights['high_confidence_patterns'][] = $key;
            }
        }
        
        voanh_log("Memory consolidation completed for user $userId", 3);
        return $insights;
    }
    
    // === CONTEXTE POUR L'IA ===
    
    public function buildContextForAI($userId, $taskType = null) {
        $soul = $this->getSoulMemory($userId);
        $personality = $this->getPersonalityMemory($userId);
        $learning = $this->getLearningMemory($userId);
        
        $context = [
            'user_identity' => $soul['identity_profile'] ?? [],
            'core_values' => $soul['core_values'] ?? [],
            'personality' => $personality,
            'relevant_knowledge' => []
        ];
        
        // Sélectionner les connaissances pertinentes selon le type de tâche
        if ($taskType && isset($learning['knowledge_base'][$taskType])) {
            $context['relevant_knowledge'] = array_slice(
                $learning['knowledge_base'][$taskType], 
                -5
            );
        }
        
        return $context;
    }
}
