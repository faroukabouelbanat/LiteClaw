<?php
/**
 * LiteClaw - Client API Mistral avec rotation des clés
 * Compatible hébergement mutualisé Hostinger (utilise cURL, pas file_get_contents)
 */

require_once __DIR__ . '/../config.php';

class MistralClient {
    private $apiKeys;
    private $currentKeyIndex = 0;
    private $keyFailures = [];
    
    public function __construct($userApiKey = null) {
        // Priorité à la clé utilisateur si fournie
        if ($userApiKey && !empty(trim($userApiKey))) {
            $this->apiKeys = [trim($userApiKey)];
        } else {
            $this->apiKeys = DEFAULT_MISTRAL_API_KEYS;
        }
        
        liteclaw_log("MistralClient initialized with " . count($this->apiKeys) . " API key(s)", 3);
    }
    
    /**
     * Sélection intelligente du modèle selon le type de tâche
     */
    public function selectBestModel($message, $selectedModel = null) {
        if ($selectedModel && $selectedModel !== 'auto') {
            return $selectedModel;
        }
        
        $messageLower = strtolower($message);
        
        // Détection du type de tâche
        if (preg_match('/(code|programm|dévelop|créer un|génère|function|class|var|const|let|echo|print|import|from)/i', $messageLower)) {
            return 'devstral-2512'; // Dev Agent Pro
        }
        
        if (preg_match('/(debug|erreur|bug|problème|corrige|fix|repair)/i', $messageLower)) {
            return 'devstral-medium-2507'; // Dev Agent Medium
        }
        
        if (preg_match('/(analyse|explain|raisonn|logique|complexe|détaill|réfléchir)/i', $messageLower)) {
            return 'mistral-large-2512'; // Mistral Brain Ultra
        }
        
        if (preg_match('/(rapide|simple|court|résumé|classify|tag)/i', $messageLower)) {
            return 'mistral-small-2603'; // Fast Automate Turbo
        }
        
        if (preg_match('/(creativ|poèm|histoire|story|imagine|invent)/i', $messageLower)) {
            return 'labs-mistral-small-creative'; // Creative Writer
        }
        
        if (preg_match('/(planifi|organise|étape|stratég|orchestr)/i', $messageLower)) {
            return 'magistral-medium-2509'; // Agent Router Medium
        }
        
        // Par défaut: modèle flagship
        return 'mistral-large-2512';
    }
    
    public function chat($messages, $model = 'mistral-large-2512', $options = []) {
        $defaults = [
            'temperature' => 0.7,
            'top_p' => 1,
            'max_tokens' => 4096,
            'stream' => false,
            'safe_prompt' => false
        ];
        
        $params = array_merge($defaults, $options);
        $params['model'] = $model;
        $params['messages'] = $messages;
        
        $maxRetries = count($this->apiKeys) * 2;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            $apiKey = $this->getCurrentApiKey();
            
            try {
                $response = $this->makeRequest($apiKey, $params);
                
                if (isset($response['choices']) && !empty($response['choices'])) {
                    return [
                        'success' => true,
                        'response' => $response,
                        'content' => $response['choices'][0]['message']['content'],
                        'model' => $model,
                        'usage' => $response['usage'] ?? []
                    ];
                }
                
                throw new Exception('Réponse invalide de l\'API');
                
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
                liteclaw_log("API call failed with key index $this->currentKeyIndex: $errorMsg", 2);
                
                // Rotation en cas d'erreur rate limit
                if (strpos($errorMsg, '429') !== false || strpos($errorMsg, 'rate limit') !== false) {
                    $this->markKeyAsFailed($this->currentKeyIndex, 'rate_limit');
                    $this->rotateKey();
                    $retryCount++;
                    continue;
                }
                
                // Rotation en cas d'erreur unauthorized
                if (strpos($errorMsg, '401') !== false || strpos($errorMsg, 'unauthorized') !== false) {
                    $this->markKeyAsFailed($this->currentKeyIndex, 'invalid');
                    $this->rotateKey();
                    $retryCount++;
                    continue;
                }
                
                // Autres erreurs, on réessaie avec la même clé une fois
                if ($retryCount % count($this->apiKeys) === 0) {
                    $retryCount++;
                    continue;
                }
                
                $this->rotateKey();
                $retryCount++;
            }
        }
        
        return ['success' => false, 'error' => 'Échec après plusieurs tentatives avec toutes les clés API'];
    }
    
    /**
     * Requête HTTP avec cURL (compatible Hostinger)
     */
    private function makeRequest($apiKey, $params) {
        $ch = curl_init(MISTRAL_API_ENDPOINT);
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: LiteClaw/1.0',
            'Accept: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30, // Timeout raisonnable pour mutualisé
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $errorMsg = $decoded['message'] ?? "HTTP $httpCode";
            throw new Exception($errorMsg);
        }
        
        return json_decode($response, true);
    }
    
    private function getCurrentApiKey() {
        return $this->apiKeys[$this->currentKeyIndex];
    }
    
    private function rotateKey() {
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
        liteclaw_log("Rotated to API key index $this->currentKeyIndex", 3);
    }
    
    private function markKeyAsFailed($keyIndex, $reason) {
        if (!isset($this->keyFailures[$keyIndex])) {
            $this->keyFailures[$keyIndex] = ['count' => 0, 'reason' => $reason];
        }
        $this->keyFailures[$keyIndex]['count']++;
        liteclaw_log("Key $keyIndex marked as failed ($reason), total failures: " . $this->keyFailures[$keyIndex]['count'], 2);
    }
    
    public function getAvailableModels() {
        return MISTRAL_MODELS;
    }
    
    public function getModelInfo($modelId) {
        foreach (MISTRAL_MODELS as $category => $models) {
            foreach ($models as $model) {
                if ($model['id'] === $modelId) {
                    return $model;
                }
            }
        }
        return null;
    }
}

function getMistralClient($userApiKey = null) {
    return new MistralClient($userApiKey);
}
