<?php
/**
 * LiteClaw LLM - PHP Version
 * Interface avec les modèles de langage (OpenAI, Groq, Ollama, etc.)
 */

require_once __DIR__ . '/config.php';

function getFullModelName(string $provider, string $model, ?string $baseUrl = null): string {
    /**
     * Normalise les noms de modèles pour LiteLLM/compatibilité.
     * - Pour OpenAI et proxies compatibles, ajoute le préfixe 'openai/'
     * - Pour autres providers, retourne le modèle tel quel
     */
    
    if ($provider === 'openai') {
        // Pour les proxies OpenAI (OpenRouter, Groq, DeepSeek, Custom)
        if ($baseUrl && strpos($baseUrl, 'api.openai.com') === false) {
            if (strpos($model, 'openai/') !== 0) {
                return 'openai/' . $model;
            }
            return $model;
        }
        
        // Flux OpenAI normal
        if (strpos($model, 'openai/') !== 0) {
            return 'openai/' . $model;
        }
        return $model;
    }
    
    // Autres providers (bedrock, huggingface, ollama, etc.)
    return $model;
}

function configureBedrockEnv(): void {
    /**
     * Configure l'environnement AWS Bedrock pour les appels API.
     * Utilise le flux de clé API :
     * - La clé est stockée dans la config LiteClaw
     * - Exportée comme AWS_BEARER_TOKEN_BEDROCK
     */
    
    global $settings;
    
    // Région pour toutes les requêtes Bedrock
    if (!empty($settings->AWS_REGION_NAME)) {
        if (!getenv('AWS_REGION_NAME')) {
            putenv('AWS_REGION_NAME=' . $settings->AWS_REGION_NAME);
        }
    }
    
    // Clé API Bedrock
    $bedrockApiKey = null;
    
    if ($settings->LLM_PROVIDER === 'bedrock' && !empty($settings->LLM_API_KEY)) {
        $bedrockApiKey = $settings->LLM_API_KEY;
    }
    
    if ($settings->VISION_LLM_PROVIDER === 'bedrock' && !empty($settings->VISION_LLM_API_KEY)) {
        $bedrockApiKey = $settings->VISION_LLM_API_KEY ?: $bedrockApiKey;
    }
    
    if ($bedrockApiKey) {
        if (!getenv('AWS_BEARER_TOKEN_BEDROCK')) {
            putenv('AWS_BEARER_TOKEN_BEDROCK=' . $bedrockApiKey);
        }
    }
}

/**
 * Appel à l'API LLM (compatible OpenAI)
 * @param string $model Le modèle à utiliser
 * @param array $messages Tableau des messages au format OpenAI
 * @param array|null $tools Outils/fonctions disponibles
 * @param bool $stream Si true, retourne un générateur pour le streaming
 * @return array|string Réponse ou générateur
 */
function callLLM(string $model, array $messages, ?array $tools = null, bool $stream = false) {
    global $settings;
    
    configureBedrockEnv();
    
    $fullModelName = getFullModelName($settings->LLM_PROVIDER, $model, $settings->LLM_BASE_URL);
    
    $payload = [
        'model' => $fullModelName,
        'messages' => $messages,
        'stream' => $stream
    ];
    
    if ($tools !== null) {
        $payload['tools'] = $tools;
        $payload['tool_choice'] = 'auto';
    }
    
    $url = $settings->LLM_BASE_URL ?: 'https://api.openai.com/v1/chat/completions';
    $apiKey = $settings->LLM_API_KEY;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    if ($stream) {
        // Pour le streaming, on utilise un callback
        $response = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$response) {
            $response .= $data;
            return strlen($data);
        });
        curl_exec($ch);
        curl_close($ch);
        return parseStreamResponse($response);
    } else {
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("LLM API Error (HTTP {$httpCode}): " . $result);
        }
        
        return json_decode($result, true);
    }
}

function parseStreamResponse(string $rawResponse): array {
    /**
     * Parse une réponse streamée SSE en tableau de chunks
     */
    $chunks = [];
    $lines = explode("\n", $rawResponse);
    
    foreach ($lines as $line) {
        if (strpos($line, 'data: ') === 0) {
            $data = substr($line, 6);
            if ($data === '[DONE]') {
                break;
            }
            $chunk = json_decode($data, true);
            if ($chunk) {
                $chunks[] = $chunk;
            }
        }
    }
    
    return $chunks;
}
