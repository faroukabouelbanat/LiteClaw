<?php
/**
 * LiteClaw Configuration - PHP Version
 * Gère la configuration et les paramètres de l'application
 */

class LiteClawConfig {
    private static ?LiteClawConfig $instance = null;
    
    // Work Directory - où LiteClaw stocke les fichiers, screenshots, configs
    public string $WORK_DIR;
    
    // LLM Configuration
    public string $LLM_PROVIDER = "openai";
    public string $LLM_API_KEY = "";
    public string $LLM_MODEL = "gpt-4o";
    public ?string $LLM_BASE_URL = null;
    public ?string $LLM_API_VERSION = null;
    
    // Vision LLM (pour Vision Agent) - Utilise le LLM principal si non défini
    public ?string $VISION_LLM_PROVIDER = null;
    public ?string $VISION_LLM_MODEL = null;
    public ?string $VISION_LLM_API_KEY = null;
    public ?string $VISION_LLM_BASE_URL = null;
    
    // AWS / Bedrock
    public ?string $AWS_REGION_NAME = null;
    
    // WhatsApp Config
    public string $WHATSAPP_TYPE = "selenium";
    public ?array $WHATSAPP_ALLOWED_NUMBERS = null;
    public ?string $TELEGRAM_BOT_TOKEN = null;
    public ?array $TELEGRAM_ALLOWED_IDS = null;
    public ?string $GIPHY_API_KEY = null;
    public ?string $SLACK_BOT_TOKEN = null;
    public ?string $SLACK_APP_TOKEN = null;
    public ?string $SLACK_SIGNING_SECRET = null;
    public string $WHATSAPP_SESSION_ID = "whatsapp";
    
    // Break Time
    public float $BREAK_UNTIL = 0;
    
    // Chrome Path
    public string $CHROME_PATH = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
    public int $CHROME_DEBUG_PORT = 9222;
    
    private function __construct() {
        $this->WORK_DIR = $this->getDefaultWorkDir();
        $this->loadConfig();
        $this->ensureWorkDirs();
    }
    
    /**
     * Get default work directory based on OS
     */
    private function getDefaultWorkDir(): string {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return "C:\\liteclaw";
        } else {
            return getenv('HOME') . "/liteclaw";
        }
    }
    
    /**
     * Load configuration from JSON file or environment variables
     */
    private function loadConfig(): void {
        $configFile = "config.json";
        $configData = [];
        
        // Try local directory first
        if (file_exists($configFile)) {
            $configData = json_decode(file_get_contents($configFile), true) ?? [];
        } elseif (file_exists($this->WORK_DIR . "/" . $configFile)) {
            $configData = json_decode(file_get_contents($this->WORK_DIR . "/" . $configFile), true) ?? [];
        }
        
        // Apply config data
        foreach ($configData as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        // Environment variables override
        if (getenv('LITECLAW_WORK_DIR')) $this->WORK_DIR = getenv('LITECLAW_WORK_DIR');
        if (getenv('LITECLAW_LLM_PROVIDER')) $this->LLM_PROVIDER = getenv('LITECLAW_LLM_PROVIDER');
        if (getenv('LITECLAW_LLM_API_KEY')) $this->LLM_API_KEY = getenv('LITECLAW_LLM_API_KEY');
        if (getenv('LITECLAW_LLM_MODEL')) $this->LLM_MODEL = getenv('LITECLAW_LLM_MODEL');
    }
    
    /**
     * Get screenshots directory path
     */
    public function getScreenshotsDir(): string {
        return $this->WORK_DIR . "/screenshots";
    }
    
    /**
     * Get configs directory path
     */
    public function getConfigsDir(): string {
        return $this->WORK_DIR . "/configs";
    }
    
    /**
     * Get notes directory path
     */
    public function getNotesDir(): string {
        return $this->WORK_DIR . "/notes";
    }
    
    /**
     * Get exports directory path
     */
    public function getExportsDir(): string {
        return $this->WORK_DIR . "/exports";
    }
    
    /**
     * Get agent instructions path
     */
    public function getAgentInstructionsPath(): string {
        return $this->getConfigsDir() . "/AGENT.md";
    }
    
    /**
     * Create work directory and subdirectories if they don't exist
     */
    public function ensureWorkDirs(): void {
        $dirs = [
            $this->WORK_DIR,
            $this->getScreenshotsDir(),
            $this->getConfigsDir(),
            $this->getNotesDir(),
            $this->getExportsDir(),
            $this->WORK_DIR . "/sessions"
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Get Chrome user data directory
     */
    public function getChromeUserDataDir(): string {
        return $this->WORK_DIR . "/sessions/browser";
    }
    
    /**
     * Singleton instance getter
     */
    public static function getInstance(): LiteClawConfig {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Initialize settings
$settings = LiteClawConfig::getInstance();
