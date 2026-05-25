<?php
/**
 * LiteClaw Meta Memory - PHP Version
 * Mémoire persistante SOUL/PERSONALITY/SUBCONSCIOUS/LEARNING
 */

require_once __DIR__ . '/config.php';

function getFilePath(string $filename): string {
    global $settings;
    
    // 1. Essayer WORK_DIR/configs
    $workPath = $settings->getConfigsDir() . '/' . $filename;
    if (file_exists($workPath)) {
        return $workPath;
    }
    
    // 2. Essayer l'emplacement legacy
    $legacyPath = __DIR__ . '/' . $filename;
    if (file_exists($legacyPath)) {
        return $legacyPath;
    }
    
    // 3. Défaut à WORK_DIR/configs pour les nouveaux fichiers
    return $workPath;
}

// Constantes de fichiers
define('AGENT_FILE', getFilePath('AGENT.md'));
define('SOUL_FILE', getFilePath('SOUL.md'));
define('PERSONALITY_FILE', getFilePath('PERSONALITY.md'));
define('SUBCONSCIOUS_FILE', getFilePath('SUBCONSCIOUS.md'));
define('LEARNING_FILE', getFilePath('LEARNING.md'));

function readFileContent(string $filepath): string {
    if (!file_exists($filepath)) {
        return '';
    }
    try {
        $content = file_get_contents($filepath);
        return $content !== false ? $content : '';
    } catch (Exception $e) {
        return '';
    }
}

function getAgentProfile(): string {
    return readFileContent(AGENT_FILE);
}

function getSoulMemory(): string {
    return readFileContent(SOUL_FILE);
}

function getPersonalityMemory(): string {
    return readFileContent(PERSONALITY_FILE);
}

function getSubconsciousMemory(): string {
    return readFileContent(SUBCONSCIOUS_FILE);
}

function getLearningMemory(): string {
    return readFileContent(LEARNING_FILE);
}

function updateSoulMemory(string $content): string {
    try {
        file_put_contents(SOUL_FILE, $content);
        return "SOUL updated successfully.";
    } catch (Exception $e) {
        return "Failed to update SOUL: " . $e->getMessage();
    }
}

function appendToSoul(string $content): string {
    try {
        file_put_contents(SOUL_FILE, "\n" . $content, FILE_APPEND);
        return "Memory appended to SOUL.";
    } catch (Exception $e) {
        return "Failed to append to SOUL: " . $e->getMessage();
    }
}

function updatePersonalityMemory(string $content): string {
    try {
        file_put_contents(PERSONALITY_FILE, $content);
        return "Personality updated successfully.";
    } catch (Exception $e) {
        return "Failed to update Personality: " . $e->getMessage();
    }
}

function updateSubconsciousMemory(string $content): string {
    try {
        file_put_contents(SUBCONSCIOUS_FILE, $content);
        return "Subconscious updated successfully.";
    } catch (Exception $e) {
        return "Failed to update Subconscious: " . $e->getMessage();
    }
}

function updateLearningMemory(string $content): string {
    try {
        file_put_contents(LEARNING_FILE, $content);
        return "Learning memory updated successfully.";
    } catch (Exception $e) {
        return "Failed to update Learning memory: " . $e->getMessage();
    }
}
