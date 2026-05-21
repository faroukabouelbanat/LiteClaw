<?php
/**
 * VoAnh - Client API Mistral avec Rotation de Clés
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Classe de gestion de l'API Mistral
 */
class MistralClient {
    private $currentKeyIndex = 0;
    private $apiKeys = [];
    private $db;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = getDb();
        $this->loadApiKeys();
    }
    
    /**
     * Charge les clés API depuis la base de données
     */
    private function loadApiKeys() {
        // Récupérer les clés actives depuis la DB
        $result = $this->db->fetchAll(
            'SELECT api_key FROM api_key_rotation WHERE is_active = 1 ORDER BY id'
        );
        
        if (!empty($result)) {
            $this->apiKeys = array_column($result, 'api_key');
        } else {
            // Fallback sur les clés par défaut
            $this->apiKeys = DEFAULT_MISTRAL_API_KEYS;
        }
        
        // Si l'utilisateur a une clé personnelle, l'ajouter en premier
        if (isset($_SESSION['user_id'])) {
            $user = $this->db->fetchOne('SELECT mistral_api_key FROM users WHERE id = ?', [$_SESSION['user_id']]);
            if (!empty($user['mistral_api_key'])) {
                // Ajouter la clé personnelle en tête de liste
                array_unshift($this->apiKeys, $user['mistral_api_key']);
            }
        }
    }
    
    /**
     * Obtient la clé API actuelle
     */
    private function getCurrentApiKey() {
        if (empty($this->apiKeys)) {
            throw new Exception('Aucune clé API disponible');
        }
        return $this->apiKeys[$this->currentKeyIndex];
    }
    
    /**
     * Passe à la clé suivante (rotation)
     */
    private function rotateKey() {
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
        return $this->getCurrentApiKey();
    }
    
    /**
     * Met à jour les statistiques d'utilisation d'une clé
     */
    private function updateKeyStats($apiKey, $isError = false) {
        try {
            if ($isError) {
                $this->db->query(
                    'UPDATE api_key_rotation SET error_count = error_count + 1, updated_at = CURRENT_TIMESTAMP WHERE api_key = ?',
                    [$apiKey]
                );
            } else {
                $this->db->query(
                    'UPDATE api_key_rotation SET requests_count = requests_count + 1, last_used = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE api_key = ?',
                    [$apiKey]
                );
            }
        } catch (Exception $e) {
            // Ignorer les erreurs de statistiques
        }
    }
    
    /**
     * Requête HTTP avec cURL (respect des limitations Hostinger)
     */
    private function makeRequest($url, $data, $apiKey) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'User-Agent: VoAnh-PHP/1.0'
            ],
            CURLOPT_TIMEOUT => MISTRAL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Erreur cURL: ' . $error);
        }
        
        return ['body' => $response, 'code' => $httpCode];
    }
    
    /**
     * Envoie une requête de chat à l'API Mistral
     */
    public function chat($messages, $model = null, $options = []) {
        $model = $model ?? DEFAULT_MODEL;
        $maxRetries = count($this->apiKeys);
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $apiKey = $this->getCurrentApiKey();
            
            $data = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0.7,
                'top_p' => $options['top_p'] ?? 1,
                'max_tokens' => $options['max_tokens'] ?? null,
                'stream' => $options['stream'] ?? false
            ];
            
            // Nettoyer les valeurs null
            $data = array_filter($data, fn($v) => $v !== null);
            
            try {
                $result = $this->makeRequest(MISTRAL_ENDPOINT, $data, $apiKey);
                
                if ($result['code'] === 429) {
                    // Rate limit - passer à la clé suivante
                    $this->updateKeyStats($apiKey, true);
                    $this->rotateKey();
                    $attempt++;
                    continue;
                }
                
                if ($result['code'] >= 400) {
                    $errorData = json_decode($result['body'], true);
                    throw new Exception('Erreur API (' . $result['code'] . '): ' . 
                        ($errorData['message'] ?? 'Erreur inconnue'));
                }
                
                // Succès
                $this->updateKeyStats($apiKey, false);
                $responseData = json_decode($result['body'], true);
                
                return [
                    'success' => true,
                    'response' => $responseData,
                    'content' => $responseData['choices'][0]['message']['content'] ?? '',
                    'model' => $responseData['model'] ?? $model,
                    'usage' => $responseData['usage'] ?? []
                ];
                
            } catch (Exception $e) {
                log_error('Erreur Mistral API: ' . $e->getMessage(), ['model' => $model, 'attempt' => $attempt]);
                $this->updateKeyStats($apiKey, true);
                
                if ($attempt >= $maxRetries - 1) {
                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'content' => ''
                    ];
                }
                
                $this->rotateKey();
                $attempt++;
            }
        }
        
        return [
            'success' => false,
            'error' => 'Toutes les clés API ont échoué',
            'content' => ''
        ];
    }
    
    /**
     * Chat avec streaming (Server-Sent Events)
     */
    public function chatStream($messages, $model = null, $options = []) {
        $model = $model ?? DEFAULT_MODEL;
        $apiKey = $this->getCurrentApiKey();
        
        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'top_p' => $options['top_p'] ?? 1,
            'stream' => true
        ];
        
        // Configuration pour le streaming avec cURL
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => MISTRAL_ENDPOINT,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'User-Agent: VoAnh-PHP/1.0',
                'Accept: text/event-stream'
            ],
            CURLOPT_TIMEOUT => MISTRAL_TIMEOUT,
            CURLOPT_WRITEFUNCTION => function($curl, $data) {
                echo $data;
                flush();
                return strlen($data);
            }
        ]);
        
        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            log_error('Erreur streaming Mistral: ' . $error);
        }
    }
    
    /**
     * Ajoute une clé API personnelle pour l'utilisateur
     */
    public function addUserApiKey($userId, $apiKey) {
        // Vérifier que la clé n'est pas déjà dans la liste
        $existing = $this->db->fetchOne(
            'SELECT id FROM users WHERE id = ? AND mistral_api_key = ?',
            [$userId, $apiKey]
        );
        
        if (!$existing) {
            $this->db->update('users', ['mistral_api_key' => $apiKey], 'id = ?', [$userId]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Liste tous les modèles disponibles
     */
    public function listModels() {
        global $MODELS;
        return $MODELS;
    }
}

// Fonction helper
function getMistralClient() {
    static $client = null;
    if ($client === null) {
        $client = new MistralClient();
    }
    return $client;
}
