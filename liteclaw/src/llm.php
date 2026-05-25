<?php
/**
 * LiteClaw LLM - PHP Version
 * Gestion des appels aux modèles de langage (LiteLLM compatible)
 */

require_once __DIR__ . '/config.php';

class LiteClawLLM {
    /**
     * Normaliser les noms de modèles pour LiteLLM
     */
    public static function getFullModelName(string $provider, string $model, ?string $baseUrl = null): string {
        if ($provider === "openai") {
            // Pour les proxies OpenAI compatibles (OpenRouter, Groq, DeepSeek, Custom)
            if ($baseUrl && strpos($baseUrl, "api.openai.com") === false) {
                if (strpos($model, "openai/") !== 0) {
                    return "openai/" . $model;
                }
                return $model;
            }
            
            // Flux OpenAI normal
            if (strpos($model, "openai/") !== 0) {
                return "openai/" . $model;
            }
            return $model;
        }
        
        // Autres providers (bedrock, huggingface, ollama, etc.)
        return $model;
    }
    
    /**
     * Configurer l'environnement AWS Bedrock pour LiteLLM
     */
    public static function configureBedrockEnv(): void {
        global $settings;
        
        // Region pour toutes les requêtes Bedrock
        if ($settings->AWS_REGION_NAME) {
            if (!getenv('AWS_REGION_NAME')) {
                putenv('AWS_REGION_NAME=' . $settings->AWS_REGION_NAME);
            }
        }
        
        // Clé API Bedrock
        $bedrockApiKey = null;
        
        if ($settings->LLM_PROVIDER === "bedrock" && $settings->LLM_API_KEY) {
            $bedrockApiKey = $settings->LLM_API_KEY;
        }
        
        if ($settings->VISION_LLM_PROVIDER === "bedrock" && $settings->VISION_LLM_API_KEY) {
            $bedrockApiKey = $settings->VISION_LLM_API_KEY ?: $bedrockApiKey;
        }
        
        if ($bedrockApiKey) {
            if (!getenv('AWS_BEARER_TOKEN_BEDROCK')) {
                putenv('AWS_BEARER_TOKEN_BEDROCK=' . $bedrockApiKey);
            }
        }
    }
    
    /**
     * Appel à un modèle LLM via cURL (compatible LiteLLM API)
     */
    public static function chatCompletion(array $messages, string $model = null, array $options = []): array {
        global $settings;
        
        $provider = $options['provider'] ?? $settings->LLM_PROVIDER;
        $modelName = $model ?? $settings->LLM_MODEL;
        $apiKey = $options['api_key'] ?? $settings->LLM_API_KEY;
        $baseUrl = $options['base_url'] ?? $settings->LLM_BASE_URL;
        
        // Normaliser le nom du modèle
        $fullModelName = self::getFullModelName($provider, $modelName, $baseUrl);
        
        // URL par défaut selon le provider
        if (!$baseUrl) {
            switch ($provider) {
                case 'openai':
                    $baseUrl = 'https://api.openai.com/v1/chat/completions';
                    break;
                case 'groq':
                    $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';
                    break;
                case 'ollama':
                    $baseUrl = 'http://localhost:11434/v1/chat/completions';
                    break;
                default:
                    $baseUrl = 'https://api.openai.com/v1/chat/completions';
            }
        }
        
        // Préparation de la requête
        $data = [
            'model' => $fullModelName,
            'messages' => $messages
        ];
        
        if (isset($options['tools'])) {
            $data['tools'] = $options['tools'];
        }
        
        if (isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }
        
        // Appel cURL
        $ch = curl_init($baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error];
        }
        
        return json_decode($response, true) ?? ['error' => 'Invalid response'];
    }
}

// Helper functions
function getFullModelName(string $provider, string $model, ?string $baseUrl = null): string {
    return LiteClawLLM::getFullModelName($provider, $model, $baseUrl);
}

function configureBedrockEnv(): void {
    LiteClawLLM::configureBedrockEnv();
}

function chatCompletion(array $messages, ?string $model = null, array $options = []): array {
    return LiteClawLLM::chatCompletion($messages, $model, $options);
}
