<?php
/**
 * LiteClaw Meta Memory - PHP Version
 * Gère les fichiers de mémoire persistante (SOUL, PERSONALITY, SUBCONSCIOUS, etc.)
 */

require_once __DIR__ . '/config.php';

class MetaMemory {
    private string $agentFile;
    private string $soulFile;
    private string $personalityFile;
    private string $subconsciousFile;
    private string $learningFile;
    
    public function __construct() {
        global $settings;
        
        $configsDir = $settings->getConfigsDir();
        
        $this->agentFile = $configsDir . "/AGENT.md";
        $this->soulFile = $configsDir . "/SOUL.md";
        $this->personalityFile = $configsDir . "/PERSONALITY.md";
        $this->subconsciousFile = $configsDir . "/SUBCONSCIOUS.md";
        $this->learningFile = $configsDir . "/LEARNING.md";
    }
    
    /**
     * Read file content safely
     */
    private function readFileContent(string $filepath): string {
        if (!file_exists($filepath)) {
            return "";
        }
        try {
            return file_get_contents($filepath);
        } catch (Exception $e) {
            return "";
        }
    }
    
    /**
     * Get agent profile
     */
    public function getAgentProfile(): string {
        return $this->readFileContent($this->agentFile);
    }
    
    /**
     * Get soul memory
     */
    public function getSoulMemory(): string {
        return $this->readFileContent($this->soulFile);
    }
    
    /**
     * Get personality memory
     */
    public function getPersonalityMemory(): string {
        return $this->readFileContent($this->personalityFile);
    }
    
    /**
     * Get subconscious memory
     */
    public function getSubconsciousMemory(): string {
        return $this->readFileContent($this->subconsciousFile);
    }
    
    /**
     * Get learning memory
     */
    public function getLearningMemory(): string {
        return $this->readFileContent($this->learningFile);
    }
    
    /**
     * Update soul memory (overwrite)
     */
    public function updateSoulMemory(string $content): string {
        try {
            file_put_contents($this->soulFile, $content);
            return "SOUL updated successfully.";
        } catch (Exception $e) {
            return "Failed to update SOUL: " . $e->getMessage();
        }
    }
    
    /**
     * Append to soul memory
     */
    public function appendToSoul(string $content): string {
        try {
            file_put_contents($this->soulFile, "\n" . $content, FILE_APPEND);
            return "Memory appended to SOUL.";
        } catch (Exception $e) {
            return "Failed to append to SOUL: " . $e->getMessage();
        }
    }
    
    /**
     * Update personality memory (overwrite)
     */
    public function updatePersonalityMemory(string $content): string {
        try {
            file_put_contents($this->personalityFile, $content);
            return "Personality updated successfully.";
        } catch (Exception $e) {
            return "Failed to update Personality: " . $e->getMessage();
        }
    }
    
    /**
     * Update subconscious memory (overwrite)
     */
    public function updateSubconsciousMemory(string $content): string {
        try {
            file_put_contents($this->subconsciousFile, $content);
            return "Subconscious updated successfully.";
        } catch (Exception $e) {
            return "Failed to update Subconscious: " . $e->getMessage();
        }
    }
    
    /**
     * Update learning memory (overwrite)
     */
    public function updateLearningMemory(string $content): string {
        try {
            file_put_contents($this->learningFile, $content);
            return "Learning memory updated successfully.";
        } catch (Exception $e) {
            return "Failed to update Learning memory: " . $e->getMessage();
        }
    }
    
    /**
     * Get file paths (for external access)
     */
    public function getFilePaths(): array {
        return [
            'agent' => $this->agentFile,
            'soul' => $this->soulFile,
            'personality' => $this->personalityFile,
            'subconscious' => $this->subconsciousFile,
            'learning' => $this->learningFile
        ];
    }
}

// Initialize meta memory
$metaMemory = new MetaMemory();

// Helper functions for compatibility
function getAgentProfile(): string {
    global $metaMemory;
    return $metaMemory->getAgentProfile();
}

function getSoulMemory(): string {
    global $metaMemory;
    return $metaMemory->getSoulMemory();
}

function getPersonalityMemory(): string {
    global $metaMemory;
    return $metaMemory->getPersonalityMemory();
}

function getSubconsciousMemory(): string {
    global $metaMemory;
    return $metaMemory->getSubconsciousMemory();
}

function getLearningMemory(): string {
    global $metaMemory;
    return $metaMemory->getLearningMemory();
}

function updateSoulMemory(string $content): string {
    global $metaMemory;
    return $metaMemory->updateSoulMemory($content);
}

function appendToSoul(string $content): string {
    global $metaMemory;
    return $metaMemory->appendToSoul($content);
}

function updatePersonalityMemory(string $content): string {
    global $metaMemory;
    return $metaMemory->updatePersonalityMemory($content);
}

function updateSubconsciousMemory(string $content): string {
    global $metaMemory;
    return $metaMemory->updateSubconsciousMemory($content);
}

function updateLearningMemory(string $content): string {
    global $metaMemory;
    return $metaMemory->updateLearningMemory($content);
}
