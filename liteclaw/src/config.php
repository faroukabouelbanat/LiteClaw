<?php
/**
 * LiteClaw Configuration - PHP Version
 * Gère tous les paramètres et configurations de l'application
 */

class Settings {
    // Work Directory - où LiteClaw stocke les fichiers, screenshots, configs
    public string $WORK_DIR;
    
    public string $LLM_PROVIDER = "openai";
    public string $LLM_API_KEY = "";  // Vide pendant l'onboarding
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
    public float $BREAK_UNTIL = 0.0;
    
    // Chrome Path
    public string $CHROME_PATH = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
    public int $CHROME_DEBUG_PORT = 9222;
    
    private static ?Settings $instance = null;
    
    private function __construct() {
        $this->WORK_DIR = self::getDefaultWorkDir();
        $this->loadFromEnv();
        $this->loadFromJson();
        $this->ensureWorkDirs();
    }
    
    public static function getInstance(): Settings {
        if (self::$instance === null) {
            self::$instance = new Settings();
        }
        return self::$instance;
    }
    
    private static function getDefaultWorkDir(): string {
        $system = php_uname('s');
        if ($system === 'Windows NT') {
            return "C:\\liteclaw";
        } else {
            return getenv('HOME') . '/liteclaw';
        }
    }
    
    private function loadFromEnv(): void {
        // Charger depuis les variables d'environnement
        if (getenv('LLM_PROVIDER')) $this->LLM_PROVIDER = getenv('LLM_PROVIDER');
        if (getenv('LLM_API_KEY')) $this->LLM_API_KEY = getenv('LLM_API_KEY');
        if (getenv('LLM_MODEL')) $this->LLM_MODEL = getenv('LLM_MODEL');
        if (getenv('LLM_BASE_URL')) $this->LLM_BASE_URL = getenv('LLM_BASE_URL');
        if (getenv('WHATSAPP_ALLOWED_NUMBERS')) {
            $nums = getenv('WHATSAPP_ALLOWED_NUMBERS');
            $this->WHATSAPP_ALLOWED_NUMBERS = array_filter(array_map('trim', explode(',', $nums)));
        }
        if (getenv('GIPHY_API_KEY')) $this->GIPHY_API_KEY = getenv('GIPHY_API_KEY');
        if (getenv('AWS_REGION_NAME')) $this->AWS_REGION_NAME = getenv('AWS_REGION_NAME');
    }
    
    private function loadFromJson(): void {
        $configFile = "config.json";
        $data = [];
        
        // Essayer le dossier local d'abord
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $data = json_decode($content, true) ?? [];
        } elseif (file_exists($this->WORK_DIR . '/' . $configFile)) {
            $content = file_get_contents($this->WORK_DIR . '/' . $configFile);
            $data = json_decode($content, true) ?? [];
        }
        
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
    
    public function ensureWorkDirs(): void {
        $dirs = [
            $this->WORK_DIR,
            $this->getScreenshotsDir(),
            $this->getConfigsDir(),
            $this->getNotesDir(),
            $this->getExportsDir(),
            $this->WORK_DIR . '/sessions'
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    public function getChromeUserDataDir(): string {
        return $this->WORK_DIR . '/sessions/browser';
    }
    
    public function getScreenshotsDir(): string {
        return $this->WORK_DIR . '/screenshots';
    }
    
    public function getConfigsDir(): string {
        return $this->WORK_DIR . '/configs';
    }
    
    public function getNotesDir(): string {
        return $this->WORK_DIR . '/notes';
    }
    
    public function getExportsDir(): string {
        return $this->WORK_DIR . '/exports';
    }
    
    public function getAgentInstructionsPath(): string {
        return $this->getConfigsDir() . '/AGENT.md';
    }
}

// Instance globale
$settings = Settings::getInstance();
