<?php
/**
 * LiteClaw-am-liorer - Générateur de Vidéos par IA
 * Module de création de scripts, storyboards et prompts vidéo
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/mistral.php';

class VideoGenerator {
    private $mistral;
    
    public function __construct($userApiKey = null) {
        $this->mistral = getMistralClient($userApiKey);
    }
    
    /**
     * Génère une production vidéo complète
     * @param array $requirements Durée, format, sujet, style
     * @return array Script + Storyboard + Prompts
     */
    public function generateVideo($requirements) {
        $duree = $requirements['duree'] ?? 60;
        $format = $requirements['format'] ?? 'YouTube';
        $sujet = $requirements['sujet'] ?? '';
        $style = $requirements['style'] ?? 'professionnel';
        
        // Génération du script
        $scriptPrompt = $this->buildScriptPrompt($sujet, $duree, $format, $style);
        $script = $this->generateWithAI($scriptPrompt);
        
        // Génération du storyboard
        $storyboardPrompt = $this->buildStoryboardPrompt($script, $duree, $format);
        $storyboard = $this->generateWithAI($storyboardPrompt);
        
        // Génération des prompts pour outils vidéo (Sora, Runway, Kling)
        $promptsPrompt = $this->buildPromptsPrompt($storyboard, $style);
        $videoPrompts = $this->generateWithAI($promptsPrompt);
        
        // Description audio
        $audioPrompt = $this->buildAudioPrompt($script, $style);
        $audioDescription = $this->generateWithAI($audioPrompt);
        
        return [
            'success' => true,
            'script' => $script,
            'storyboard' => $storyboard,
            'video_prompts' => $videoPrompts,
            'audio_description' => $audioDescription,
            'metadata' => [
                'duree_secondes' => $duree,
                'format' => $format,
                'style' => $style,
                'sujet' => $sujet
            ]
        ];
    }
    
    /**
     * Construit le prompt pour générer le script vidéo
     */
    private function buildScriptPrompt($sujet, $duree, $format, $style) {
        $wordsPerMinute = $this->getWordsPerMinute($format);
        $estimatedWords = floor(($duree / 60) * $wordsPerMinute);
        
        return "Tu es un scénariste professionnel spécialisé dans la création de vidéos {$format}.
        
        Crée un script vidéo COMPLET et DÉTAILLÉ avec les caractéristiques suivantes :
        - Sujet : {$sujet}
        - Durée : {$duree} secondes
        - Format : {$format}
        - Style : {$style}
        - Nombre de mots estimé : ~{$estimatedWords} mots
        
        Structure attendue :
        1. ACCROCHE (0-5s) : Phrase choc pour capter l'attention
        2. INTRODUCTION (5-15s) : Présentation du sujet
        3. DÉVELOPPEMENT (15-{$this->getDevelopmentTime($duree)}s) : Contenu principal divisé en points clés
        4. CONCLUSION ({$this->getConclusionTime($duree)}s-fin) : Résumé et appel à l'action
        
        Pour chaque section, indique :
        - [TIMING] Durée exacte
        - [VISUEL] Description de ce qu'on voit
        - [AUDIO] Texte exact de la voix off
        - [NOTES] Instructions particulières
        
        Le script doit être engageant, rythmé et adapté au format {$format}.
        Rédige en français.";
    }
    
    /**
     * Construit le prompt pour générer le storyboard
     */
    private function buildStoryboardPrompt($script, $duree, $format) {
        return "Tu es un directeur artistique et storyboarder professionnel.
        
        À partir du script suivant :
        {$script}
        
        Crée un STORYBOARD SCÈNE PAR SCÈNE détaillé pour une vidéo de {$duree} secondes au format {$format}.
        
        Pour CHAQUE scène, fournis :
        
        Scène X : [Titre de la scène]
        ├── Durée : X secondes (de 0:00 à 0:00)
        ├── Description visuelle : [Décris précisément ce qu'on voit : cadrage, mouvements, éléments visuels]
        ├── Action : [Ce qui se passe dans la scène]
        ├── Émotion/Atmosphère : [Ambiance visuelle]
        ├── Transition : [Vers la scène suivante]
        └── Notes techniques : [Lumières, couleurs dominantes, effets spéciaux si besoin]
        
        Découpe la vidéo en scènes cohérentes (généralement 1 scène = 3-10 secondes).
        Sois extrêmement précis dans les descriptions visuelles.
        
        Présente le tout de manière claire et structurée.
        Rédige en français.";
    }
    
    /**
     * Construit le prompt pour générer les prompts vidéo IA
     */
    private function buildPromptsPrompt($storyboard, $style) {
        return "Tu es un expert en génération de vidéos par IA (Sora, Runway Gen-2, Kling, Pika).
        
        À partir de ce storyboard :
        {$storyboard}
        
        Génère des PROMPTS OPTIMISÉS pour chaque scène, adaptés aux différents outils :
        
        Pour CHAQUE scène, crée 3 variantes de prompts :
        
        1. PROMPT SORA (OpenAI) :
           - Style : cinématographique, très détaillé
           - Inclut : mouvement de caméra, éclairage, ambiance
           - Format : description narrative fluide
           
        2. PROMPT RUNWAY :
           - Style : technique et précis
           - Inclut : motion brush hints, camera movement
           - Format : instructions claires et concises
           
        3. PROMPT KLING :
           - Style : descriptif riche
           - Inclut : détails visuels, transitions
           - Format : paragraphes structurés
           
        Style général de la vidéo : {$style}
        
        Pour chaque prompt, ajoute :
        - Negative prompt (ce qu'il faut éviter)
        - Parameters suggérés (aspect ratio, fps, duration)
        
        Rédige en français mais garde les termes techniques en anglais.";
    }
    
    /**
     * Construit le prompt pour la description audio
     */
    private function buildAudioPrompt($script, $style) {
        return "Tu es un ingénieur du son et directeur musical.
        
        À partir de ce script vidéo :
        {$script}
        
        Crée une DESCRIPTION AUDIO COMPLÈTE incluant :
        
        1. MUSIQUE DE FOND :
           - Genre musical adapté au style '{$style}'
           - Évolution de la musique (intro, buildup, climax, outro)
           - Références musicales concrètes
           - Timestamps précis des changements
           
        2. EFFETS SONORES (SFX) :
           - Liste de tous les SFX nécessaires
           - Timestamp exact pour chaque effet
           - Description précise de chaque son
           - Intensité et durée
           
        3. VOIX OFF :
           - Type de voix recommandé (homme/femme, âge, ton)
           - Instructions de jeu (rythme, intonation, émotions)
           - Points de respiration
           - Variations de volume et de débit
           
        4. MIXAGE :
           - Niveaux relatifs (musique vs voix vs SFX)
           - Effets de traitement (EQ, compression, reverb)
           - Transitions audio
           
        Sois extrêmement précis et professionnel.
        Rédige en français.";
    }
    
    /**
     * Appelle l'API Mistral pour générer du contenu
     */
    private function generateWithAI($prompt) {
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un assistant expert en production vidéo. Tu livres toujours du contenu COMPLET et DÉTAILLÉ, jamais des extraits. Tu rédiges en français.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $result = $this->mistral->chat($messages, 'labs-mistral-small-creative', [
            'temperature' => 0.8,
            'max_tokens' => 8192
        ]);
        
        return $result['success'] ? $result['content'] : '';
    }
    
    /**
     * Helpers pour le calcul des timings
     */
    private function getWordsPerMinute($format) {
        $rates = [
            'Reels' => 150,
            'TikTok' => 150,
            'YouTube' => 130,
            'pub' => 140,
            'présentation' => 120,
            'tutoriel' => 110
        ];
        return $rates[$format] ?? 130;
    }
    
    private function getDevelopmentTime($totalDuration) {
        return max(15, $totalDuration - 20);
    }
    
    private function getConclusionTime($totalDuration) {
        return max(5, $totalDuration - 10);
    }
    
    /**
     * Formate le storyboard pour affichage
     */
    public function formatStoryboard($storyboard) {
        // Parsing et formatage du storyboard
        return [
            'formatted' => true,
            'content' => $storyboard,
            'scenes_count' => substr_count($storyboard, 'Scène')
        ];
    }
}
