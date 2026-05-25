<?php
/**
 * LiteClaw-am-liorer - Générateur de Sites Web par IA
 * Module de création de sites internet complets (HTML + CSS + JS)
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/mistral.php';

class SiteGenerator {
    private $mistral;
    
    public function __construct($userApiKey = null) {
        $this->mistral = getMistralClient($userApiKey);
    }
    
    /**
     * Génère un site web complet basé sur les besoins de l'utilisateur
     * @param array $requirements Secteur, objectif, style, couleurs
     * @return array Structure complète du site
     */
    public function generateWebsite($requirements) {
        // Extraction des informations
        $secteur = $requirements['secteur'] ?? 'général';
        $objectif = $requirements['objectif'] ?? 'présentation';
        $style = $requirements['style'] ?? 'moderne';
        $couleurs = $requirements['couleurs'] ?? ['primaire' => '#58a6ff', 'secondaire' => '#0d1117'];
        
        // Génération de la structure avec l'IA
        $structurePrompt = $this->buildStructurePrompt($secteur, $objectif, $style);
        $structure = $this->generateWithAI($structurePrompt);
        
        // Génération du HTML
        $htmlPrompt = $this->buildHTMLPrompt($structure, $style, $couleurs);
        $html = $this->generateWithAI($htmlPrompt);
        
        // Génération du CSS
        $cssPrompt = $this->buildCSSPrompt($style, $couleurs);
        $css = $this->generateWithAI($cssPrompt);
        
        // Génération du JavaScript
        $jsPrompt = $this->buildJSPrompt($objectif);
        $js = $this->generateWithAI($jsPrompt);
        
        return [
            'success' => true,
            'structure' => json_decode($structure, true),
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'metadata' => [
                'secteur' => $secteur,
                'objectif' => $objectif,
                'style' => $style,
                'couleurs' => $couleurs,
                'responsive' => true,
                'seo_optimized' => true
            ]
        ];
    }
    
    /**
     * Construit le prompt pour générer la structure du site
     */
    private function buildStructurePrompt($secteur, $objectif, $style) {
        return "Tu es un expert en architecture de sites web. 
        Crée la structure JSON complète d'un site web pour le secteur '{$secteur}' 
        avec l'objectif '{$objectif}' dans un style '{$style}'.
        
        Retourne UNIQUEMENT un JSON valide avec cette structure :
        {
            \"pages\": [
                {
                    \"name\": \"Accueil\",
                    \"slug\": \"index\",
                    \"sections\": [\"hero\", \"features\", \"testimonials\", \"cta\"],
                    \"seo_title\": \"...\",
                    \"seo_description\": \"...\"
                }
            ],
            \"navigation\": {
                \"header\": [...],
                \"footer\": [...]
            },
            \"features_speciales\": []
        }
        
        Le site doit être responsive, rapide et SEO-optimisé.
        Commente chaque section en français.";
    }
    
    /**
     * Construit le prompt pour générer le HTML
     */
    private function buildHTMLPrompt($structure, $style, $couleurs) {
        $structureJson = is_array($structure) ? json_encode($structure) : $structure;
        return "Tu es un développeur front-end expert. 
        Génère le code HTML5 complet et sémantique pour un site web avec cette structure :
        {$structureJson}
        
        Style : {$style}
        Couleurs principales : " . json_encode($couleurs) . "
        
        Exigences :
        - HTML5 valide et sémantique
        - Classes BEM pour le CSS
        - Attributs ARIA pour l'accessibilité
        - Meta tags SEO complets (title, description, Open Graph)
        - Structure responsive mobile-first
        - Commentaires en français pour chaque section
        
        Livre le code HTML COMPLET, pas d'extraits.";
    }
    
    /**
     * Construit le prompt pour générer le CSS
     */
    private function buildCSSPrompt($style, $couleurs) {
        return "Tu es un expert CSS moderne. 
        Génère le code CSS complet pour un site web avec :
        
        Style : {$style}
        Palette de couleurs : " . json_encode($couleurs) . "
        
        Exigences :
        - Variables CSS personnalisées (custom properties)
        - Flexbox et Grid pour la mise en page
        - Media queries pour le responsive design
        - Animations subtiles et modernes
        - Optimisation des performances (minification mentale)
        - Support des modes clair/sombre
        - Commentaires en français
        
        Livre le code CSS COMPLET, organisé et commenté.";
    }
    
    /**
     * Construit le prompt pour générer le JavaScript
     */
    private function buildJSPrompt($objectif) {
        return "Tu es un développeur JavaScript senior. 
        Génère le code JavaScript moderne (ES6+) pour un site web avec l'objectif : {$objectif}
        
        Exigences :
        - JavaScript modulaire et propre
        - Gestion des événements DOM
        - Formulaires avec validation
        - Animations interactives
        - Lazy loading si nécessaire
        - Analytics integration ready
        - Commentaires en français
        
        Livre le code JavaScript COMPLET et fonctionnel.";
    }
    
    /**
     * Appelle l'API Mistral pour générer du contenu
     */
    private function generateWithAI($prompt) {
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un assistant expert en développement web. Tu livres toujours du code COMPLET, jamais des extraits. Tu commentes en français.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $result = $this->mistral->chat($messages, 'devstral-2512', [
            'temperature' => 0.7,
            'max_tokens' => 8192
        ]);
        
        return $result['success'] ? $result['content'] : '';
    }
    
    /**
     * Vérifie la qualité du code généré
     */
    public function validateCode($html, $css, $js) {
        $issues = [];
        
        // Validation HTML basique
        if (strpos($html, '<!DOCTYPE html>') === false) {
            $issues[] = 'Missing DOCTYPE declaration';
        }
        if (strpos($html, '<html') === false || strpos($html, '</html>') === false) {
            $issues[] = 'HTML structure incomplete';
        }
        if (strpos($html, '<head>') === false || strpos($html, '</head>') === false) {
            $issues[] = 'Missing head section';
        }
        if (strpos($html, '<body>') === false || strpos($html, '</body>') === false) {
            $issues[] = 'Missing body section';
        }
        
        // Validation CSS basique
        if (empty(trim($css))) {
            $issues[] = 'CSS is empty';
        }
        
        // Validation JS basique
        if (empty(trim($js))) {
            $issues[] = 'JavaScript is empty';
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
}
