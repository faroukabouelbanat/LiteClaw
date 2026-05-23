<?php
/**
 * VoAnh - Utilitaires de Navigateur (sans exec/system)
 */

require_once __DIR__ . '/database.php';

class BrowserUtils {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Récupérer le contenu d'une URL via cURL
    public function fetchUrl($url, $options = []) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'URL invalide'];
        }
        
        // Vérifier le protocole
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return ['success' => false, 'error' => 'Protocole non autorisé'];
        }
        
        $ch = curl_init($url);
        
        $defaults = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (VoAnh Browser Utils)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: fr-FR,fr;q=0.9'
            ]
        ];
        
        curl_setopt_array($ch, array_merge($defaults, $options['curl'] ?? []));
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => $error];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "HTTP $httpCode"];
        }
        
        // Extraire les métadonnées
        $title = $this->extractTitle($content);
        $description = $this->extractMetaDescription($content);
        $links = $this->extractLinks($content, $url);
        
        // Sauvegarder dans l'historique
        $this->saveToHistory($url, $title, $content);
        
        return [
            'success' => true,
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'links' => $links,
            'content_length' => strlen($content)
        ];
    }
    
    private function extractTitle($html) {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return 'Page sans titre';
    }
    
    private function extractMetaDescription($html) {
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\']/i', $html, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    private function extractLinks($html, $baseUrl) {
        $links = [];
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                // Convertir les URLs relatives en absolues
                if (strpos($href, 'http') !== 0) {
                    $href = $this->resolveUrl($href, $baseUrl);
                }
                $links[] = $href;
            }
        }
        return array_unique($links);
    }
    
    private function resolveUrl($relative, $base) {
        if (strpos($relative, '//') === 0) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'http') . ':' . $relative;
        }
        if (strpos($relative, '/') === 0) {
            $parsed = parse_url($base);
            return $parsed['scheme'] . '://' . $parsed['host'] . $relative;
        }
        $base = rtrim($base, '/');
        return $base . '/' . $relative;
    }
    
    private function saveToHistory($url, $title, $content) {
        if (!$this->isAuthenticated()) return;
        
        $userId = $_SESSION['user_id'];
        
        // Limiter la taille du snapshot
        $snapshot = substr($content, 0, 65535);
        
        $this->db->insert('browser_history', [
            ':user_id' => $userId,
            ':url' => $url,
            ':title' => $title,
            ':content_snapshot' => $snapshot
        ]);
    }
    
    public function getHistory($limit = 50) {
        if (!$this->isAuthenticated()) return [];
        
        $userId = $_SESSION['user_id'];
        return $this->db->fetchAll(
            "SELECT * FROM browser_history WHERE user_id = :user_id ORDER BY visited_at DESC LIMIT :limit",
            [':user_id' => $userId, ':limit' => $limit]
        );
    }
    
    public function searchHistory($query) {
        if (!$this->isAuthenticated()) return [];
        
        $userId = $_SESSION['user_id'];
        return $this->db->fetchAll(
            "SELECT * FROM browser_history 
             WHERE user_id = :user_id AND (url LIKE :query OR title LIKE :query)
             ORDER BY visited_at DESC LIMIT 50",
            [':user_id' => $userId, ':query' => '%' . $query . '%']
        );
    }
    
    private function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
}
