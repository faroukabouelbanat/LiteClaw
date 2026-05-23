<?php
/**
 * VoAnh - Client API Mistral avec rotation des clés
 */

require_once __DIR__ . '/database.php';

class MistralClient {
    private $db;
    private $apiKeys;
    private $currentKeyIndex = 0;
    private $keyFailures = [];
    
    public function __construct($userApiKey = null) {
        $this->db = Database::getInstance();
        
        // Priorité à la clé utilisateur si fournie
        if ($userApiKey && !empty(trim($userApiKey))) {
            $this->apiKeys = [trim($userApiKey)];
        } else {
            $this->apiKeys = DEFAULT_MISTRAL_API_KEYS;
        }
        
        voanh_log("MistralClient initialized with " . count($this->apiKeys) . " API key(s)", 3);
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
                voanh_log("API call failed with key index $this->currentKeyIndex: $errorMsg", 2);
                
                // Rotation en cas d'erreur
                if (strpos($errorMsg, '429') !== false || strpos($errorMsg, 'rate limit') !== false) {
                    $this->markKeyAsFailed($this->currentKeyIndex, 'rate_limit');
                    $this->rotateKey();
                    $retryCount++;
                    continue;
                }
                
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
    
    public function chatStream($messages, $model = 'mistral-large-2512', $options = [], $callback = null) {
        $options['stream'] = true;
        $defaults = [
            'temperature' => 0.7,
            'top_p' => 1,
            'max_tokens' => 4096,
            'safe_prompt' => false
        ];
        
        $params = array_merge($defaults, $options);
        $params['model'] = $model;
        $params['messages'] = $messages;
        
        $apiKey = $this->getCurrentApiKey();
        
        try {
            $ch = curl_init(MISTRAL_API_ENDPOINT);
            
            $headers = [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'User-Agent: VoAnh/1.0'
            ];
            
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($params),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_WRITEFUNCTION => function($curl, $data) use ($callback) {
                    if ($callback && strlen(trim($data)) > 0) {
                        call_user_func($callback, $data);
                    }
                    return strlen($data);
                },
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return ['success' => true];
            }
            
            throw new Exception("HTTP Error: $httpCode");
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function makeRequest($apiKey, $params) {
        $ch = curl_init(MISTRAL_API_ENDPOINT);
        
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: VoAnh/1.0',
            'Accept: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
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
        voanh_log("Rotated to API key index $this->currentKeyIndex", 3);
    }
    
    private function markKeyAsFailed($keyIndex, $reason) {
        if (!isset($this->keyFailures[$keyIndex])) {
            $this->keyFailures[$keyIndex] = ['count' => 0, 'reason' => $reason];
        }
        $this->keyFailures[$keyIndex]['count']++;
        voanh_log("Key $keyIndex marked as failed ($reason), total failures: " . $this->keyFailures[$keyIndex]['count'], 2);
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
