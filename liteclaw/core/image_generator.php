<?php
/**
 * LiteClaw-am-liorer - Générateur d'Images par IA
 * Module de création de prompts pour Midjourney, DALL-E, Stable Diffusion
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/mistral.php';

class ImageGenerator {
    private $mistral;
    
    public function __construct($userApiKey = null) {
        $this->mistral = getMistralClient($userApiKey);
    }
    
    /**
     * Génère des prompts d'image optimisés
     * @param string $description Description de l'image souhaitée
     * @return array Prompts pour différents outils + variantes de style
     */
    public function generateImagePrompts($description) {
        // Analyse de la demande
        $analysisPrompt = $this->buildAnalysisPrompt($description);
        $analysis = $this->generateWithAI($analysisPrompt);
        
        // Prompt visuel détaillé
        $detailedPrompt = $this->generateDetailedPrompt($description, $analysis);
        
        // Variantes de style
        $realisticPrompt = $this->generateStyleVariant($description, 'réaliste');
        $illustrativePrompt = $this->generateStyleVariant($description, 'illustratif');
        $minimalistPrompt = $this->generateStyleVariant($description, 'minimaliste');
        
        // Optimisation pour Midjourney
        $midjourneyPrompt = $this->optimizeForMidjourney($detailedPrompt);
        
        // Optimisation pour DALL-E 3
        $dallePrompt = $this->optimizeForDalle($detailedPrompt);
        
        // Optimisation pour Stable Diffusion
        $stableDiffusionPrompt = $this->optimizeForStableDiffusion($detailedPrompt);
        
        return [
            'success' => true,
            'analysis' => $analysis,
            'detailed_prompt' => $detailedPrompt,
            'style_variants' => [
                'realiste' => $realisticPrompt,
                'illustratif' => $illustrativePrompt,
                'minimaliste' => $minimalistPrompt
            ],
            'optimized_prompts' => [
                'midjourney' => $midjourneyPrompt,
                'dalle' => $dallePrompt,
                'stable_diffusion' => $stableDiffusionPrompt
            ],
            'metadata' => [
                'description_originale' => $description
            ]
        ];
    }
    
    /**
     * Construit le prompt d'analyse
     */
    private function buildAnalysisPrompt($description) {
        return "Tu es un expert en analyse visuelle et génération d'images par IA.
        
        Analyse cette description d'image : \"{$description}\"
        
        Extrais et liste :
        1. SUJET PRINCIPAL : Qu'est-ce qui doit apparaître au centre ?
        2. CONTEXTE/ENVIRONNEMENT : Où se situe la scène ?
        3. ÉLÉMENTS SECONDAIRES : Quels détails enrichissent l'image ?
        4. AMBIANCE/ATMOSPHÈRE : Quelle émotion doit transmettre l'image ?
        5. PALETTE DE COULEURS : Quelles couleurs dominantes ?
        6. STYLE ARTISTIQUE : Quel genre artistique convient le mieux ?
        7. COMPOSITION : Comment organiser les éléments (règle des tiers, symétrie, etc.) ?
        8. ÉCLAIRAGE : Quel type d'éclairage (naturel, studio, dramatique, doux) ?
        
        Sois précis et détaillé dans ton analyse.
        Rédige en français.";
    }
    
    /**
     * Génère un prompt visuel détaillé
     */
    private function generateDetailedPrompt($description, $analysis) {
        return "Tu es un expert en prompting pour générateurs d'images IA.
        
        Crée un PROMPT VISUEL ULTRA-DÉTAILLÉ basé sur :
        
        Description originale : {$description}
        
        Analyse : {$analysis}
        
        Ton prompt doit inclure TOUS ces éléments dans un paragraphe fluide et descriptif :
        
        Structure du prompt :
        [SUJET PRINCIPAL avec détails précis] + [ACTION/POSE si applicable] + 
        [ENVIRONNEMENT/CONTEXTE riche] + [ÉLÉMENTS SECONDAIRES pertinents] + 
        [STYLE ARTISTIQUE spécifique] + [PALETTE DE COULEURS] + 
        [TYPE D'ÉCLAIRAGE] + [COMPOSITION/CADRAGE] + [QUALITÉ/DÉTAILS TECHNIQUES]
        
        Exemple de structure :
        \"A [sujet détaillé], [action/pose], in [environnement riche avec détails], 
        surrounded by [éléments secondaires], [style artistique] style, 
        color palette of [couleurs], lit by [éclairage], composed with [composition], 
        ultra detailed, 8k resolution, professional quality\"
        
        Le prompt final doit être en ANGLAIS (langue optimale pour les IA génératrices).
        Il doit faire entre 50 et 150 mots, très descriptif mais pas trop long.
        Utilise des adjectifs puissants et évocateurs.";
    }
    
    /**
     * Génère une variante de style
     */
    private function generateStyleVariant($description, $style) {
        $styleInstructions = [
            'réaliste' => "Style PHOTORÉALISTE. L'image doit ressembler à une photographie professionnelle. 
            Utilise : photographie, realistic, photorealistic, highly detailed, 8k, professional photography, 
            natural lighting, depth of field, shot on DSLR.",
            
            'illustratif' => "Style ILLUSTRATION ARTISTIQUE. L'image doit avoir un aspect dessiné/peint. 
            Utilise : digital illustration, concept art, painted style, artistic, stylized, 
            vibrant colors, clean lines, professional illustration.",
            
            'minimaliste' => "Style MINIMALISTE ÉPURÉ. L'image doit être simple et épurée. 
            Utilise : minimalist, clean, simple composition, negative space, flat design, 
            limited color palette, geometric shapes, modern, elegant."
        ];
        
        $prompt = "Tu es un expert en styles artistiques pour l'IA générative.
        
        Crée un prompt optimisé pour une image avec le style '{$style}' basée sur : {$description}
        
        Instructions de style : {$styleInstructions[$style]}
        
        Génère un prompt complet en ANGLAIS qui intègre parfaitement ce style.
        Le prompt doit faire 40-80 mots, précis et évocateur.";
        
        return $this->generateWithAI($prompt);
    }
    
    /**
     * Optimise pour Midjourney
     */
    private function optimizeForMidjourney($prompt) {
        return "Tu es un expert en optimisation de prompts pour Midjourney v6.
        
        Prends ce prompt de base : {$prompt}
        
        Optimise-le spécifiquement pour Midjourney en ajoutant :
        1. Paramètres Midjourney à la fin : --ar 16:9 --v 6.0 --style raw --q 2
        2. Mots-clés qui fonctionnent bien avec Midjourney
        3. Structure adaptée à la compréhension de Midjourney
        
        Renvoie UNIQUEMENT le prompt final optimisé prêt à copier-coller dans Midjourney.
        Format : /imagine prompt: [TON PROMPT OPTIMISÉ] --ar 16:9 --v 6.0 --style raw";
    }
    
    /**
     * Optimise pour DALL-E 3
     */
    private function optimizeForDalle($prompt) {
        return "Tu es un expert en optimisation de prompts pour DALL-E 3.
        
        Prends ce prompt de base : {$prompt}
        
        Optimise-le spécifiquement pour DALL-E 3 :
        1. DALL-E préfère les descriptions naturelles et conversationnelles
        2. Sois précis mais pas trop technique
        3. Décris clairement ce que tu veux voir
        4. DALL-E comprend bien les contextes et scènes narratives
        
        Renvoie UNIQUEMENT le prompt final optimisé prêt à utiliser avec DALL-E 3.
        Le prompt doit être en anglais, naturel et descriptif.";
    }
    
    /**
     * Optimise pour Stable Diffusion
     */
    private function optimizeForStableDiffusion($prompt) {
        return "Tu es un expert en optimisation de prompts pour Stable Diffusion XL/SD 1.5.
        
        Prends ce prompt de base : {$prompt}
        
        Optimise-le pour Stable Diffusion :
        1. Structure : (sujet principal), [détails], {qualifiers}
        2. Ajoute des weightings entre parenthèses : (important:1.3)
        3. Inclus un negative prompt pertinent
        4. Ajoute des keywords de qualité : masterpiece, best quality, ultra detailed
        
        Fournis DEUX parties :
        POSITIVE PROMPT: [Ton prompt optimisé avec weights]
        NEGATIVE PROMPT: [low quality, worst quality, blurry, distorted, etc.]
        
        Format prêt pour Automatic1111 ou ComfyUI.";
    }
    
    /**
     * Appelle l'API Mistral pour générer du contenu
     */
    private function generateWithAI($prompt) {
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un assistant expert en génération d\'images par IA. Tu livres toujours des prompts COMPLETS et OPTIMISÉS. Tu rédiges en français sauf les prompts finaux qui sont en anglais.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $result = $this->mistral->chat($messages, 'pixtral-large-2411', [
            'temperature' => 0.7,
            'max_tokens' => 4096
        ]);
        
        return $result['success'] ? $result['content'] : '';
    }
    
    /**
     * Génère des idées de variations créatives
     */
    public function generateVariations($basePrompt, $count = 3) {
        $variationsPrompt = "Tu es un expert en variations créatives pour l'IA générative.
        
        À partir de ce prompt de base : {$basePrompt}
        
        Génère {$count} VARIATIONS CRÉATIVES distinctes qui :
        1. Gardent le sujet principal
        2. Changent l'angle, le style, ou l'atmosphère
        3. Chaque variation doit être unique et intéressante
        4. Formats : portrait, paysage, macro, aerial view, etc.
        
        Pour chaque variation, fournis :
        - Nom de la variation
        - Prompt complet en anglais
        - Brève explication de ce qui change
        
        Rédige les explications en français, les prompts en anglais.";
        
        return $this->generateWithAI($variationsPrompt);
    }
}
