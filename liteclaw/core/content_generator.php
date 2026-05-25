<?php
/**
 * LiteClaw-am-liorer - Générateur de Contenu & Rédaction IA
 * Module de création de contenu optimisé SEO
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/mistral.php';

class ContentGenerator {
    private $mistral;
    
    public function __construct($userApiKey = null) {
        $this->mistral = getMistralClient($userApiKey);
    }
    
    /**
     * Génère du contenu selon le type demandé
     * @param string $type Article, script, post réseaux sociaux, etc.
     * @param array $requirements Sujet, ton, longueur, mots-clés SEO
     * @return array Contenu généré + métadonnées SEO
     */
    public function generateContent($type, $requirements) {
        switch ($type) {
            case 'article':
                return $this->generateArticle($requirements);
            case 'script':
                return $this->generateScript($requirements);
            case 'post_reseaux_sociaux':
                return $this->generateSocialPost($requirements);
            case 'page_web':
                return $this->generateWebPage($requirements);
            case 'newsletter':
                return $this->generateNewsletter($requirements);
            default:
                return $this->generateGenericContent($type, $requirements);
        }
    }
    
    /**
     * Génère un article de blog optimisé SEO
     */
    private function generateArticle($requirements) {
        $sujet = $requirements['sujet'] ?? '';
        $ton = $requirements['ton'] ?? 'professionnel';
        $longueur = $requirements['longueur'] ?? 'moyen';
        $motsCles = $requirements['mots_cles'] ?? [];
        
        $prompt = $this->buildArticlePrompt($sujet, $ton, $longueur, $motsCles);
        $content = $this->generateWithAI($prompt);
        
        // Génération des métadonnées SEO
        $seoPrompt = $this->buildSEOPrompt($content, $motsCles);
        $seoData = $this->generateWithAI($seoPrompt);
        
        return [
            'success' => true,
            'type' => 'article',
            'content' => $content,
            'seo_metadata' => json_decode($seoData, true),
            'metadata' => [
                'sujet' => $sujet,
                'ton' => $ton,
                'longueur' => $longueur,
                'mots_cles' => $motsCles
            ]
        ];
    }
    
    /**
     * Génère un script (vidéo, podcast, présentation)
     */
    private function generateScript($requirements) {
        $type = $requirements['type_script'] ?? 'vidéo YouTube';
        $sujet = $requirements['sujet'] ?? '';
        $duree = $requirements['duree'] ?? 5;
        $ton = $requirements['ton'] ?? 'engageant';
        
        $prompt = "Tu es un scénariste professionnel spécialisé en {$type}.
        
        Écris un SCRIPT COMPLET et PRÊT À L'EMPLOI avec :
        - Sujet : {$sujet}
        - Durée estimée : {$duree} minutes
        - Ton : {$ton}
        
        Structure requise :
        1. TITRE ACCROCHEUR (3 propositions)
        2. INTRODUCTION (hook puissant pour captiver dès les premières secondes)
        3. DÉVELOPPEMENT (contenu principal structuré en points clairs)
        4. CONCLUSION (résumé + appel à l'action)
        5. NOTES DE PRODUCTION (indications pour le tournage/enregistrement)
        
        Inclus :
        - Timing estimé pour chaque section
        - Indications de ton et d'intonation
        - Suggestions visuelles ou sonores
        - Transitions fluides entre les parties
        
        Le script doit être naturel, fluide et facile à oraliser.
        Rédige en français.";
        
        $content = $this->generateWithAI($prompt);
        
        return [
            'success' => true,
            'type' => 'script',
            'content' => $content,
            'metadata' => [
                'type_script' => $type,
                'sujet' => $sujet,
                'duree_minutes' => $duree,
                'ton' => $ton
            ]
        ];
    }
    
    /**
     * Génère un post pour réseaux sociaux
     */
    private function generateSocialPost($requirements) {
        $plateforme = $requirements['plateforme'] ?? 'LinkedIn';
        $sujet = $requirements['sujet'] ?? '';
        $objectif = $requirements['objectif'] ?? 'engagement';
        $ton = $requirements['ton'] ?? 'professionnel';
        
        $prompt = "Tu es un expert en social media marketing pour {$plateforme}.
        
        Crée un POST COMPLET ET OPTIMISÉ pour {$plateforme} avec :
        - Sujet : {$sujet}
        - Objectif : {$objectif} (viralité, engagement, conversion, notoriété)
        - Ton : {$ton}
        
        Fournis :
        1. TEXTE DU POST :
           - Accroche puissante (première ligne cruciale sur {$plateforme})
           - Corps du message structuré et aéré
           - Call-to-action clair
           - Longueur adaptée à {$plateforme}
        
        2. HASHTAGS :
           - 5-10 hashtags pertinents et populaires
           - Mix de hashtags larges et nichés
           - Spécifiques à {$plateforme}
        
        3. SUGGESTIONS VISUELLES :
           - Type de visuel recommandé (photo, infographic, video, carousel)
           - Description du visuel idéal
           - Dimensions recommandées
        
        4. MEILLEUR MOMENT POUR PUBLIER :
           - Jour et heure optimaux pour {$plateforme}
           - Justification
        
        Respecte les codes et meilleures pratiques de {$plateforme}.
        Le post doit être prêt à publier.
        Rédige en français.";
        
        $content = $this->generateWithAI($prompt);
        
        return [
            'success' => true,
            'type' => 'post_reseaux_sociaux',
            'content' => $content,
            'metadata' => [
                'plateforme' => $plateforme,
                'sujet' => $sujet,
                'objectif' => $objectif,
                'ton' => $ton
            ]
        ];
    }
    
    /**
     * Génère une page web (landing page, page produit, etc.)
     */
    private function generateWebPage($requirements) {
        $type = $requirements['type_page'] ?? 'landing page';
        $sujet = $requirements['sujet'] ?? '';
        $objectif = $requirements['objectif'] ?? 'conversion';
        $ton = $requirements['ton'] ?? 'professionnel';
        $motsCles = $requirements['mots_cles'] ?? [];
        
        $prompt = "Tu es un copywriter expert en pages web optimisées pour la conversion.
        
        Écris le CONTENU COMPLET d'une {$type} avec :
        - Sujet/Produit/Service : {$sujet}
        - Objectif principal : {$objectif}
        - Ton : {$ton}
        - Mots-clés SEO : " . implode(', ', $motsCles) . "
        
        Structure AIDA (Attention, Interest, Desire, Action) :
        
        1. HERO SECTION :
           - Titre principal (H1) percutant
           - Sous-titre explicatif
           - Call-to-action principal
        
        2. PROBLÈME/DOULEUR :
           - Identification du problème du client
           - Empathie et compréhension
        
        3. SOLUTION/BÉNÉFICES :
           - Présentation de la solution
           - Bénéfices clés (pas juste features)
           - Preuves sociales (placeholders)
        
        4. FEATURES/DÉTAILS :
           - Caractéristiques principales
           - Comment ça marche
        
        5. PREUVE SOCIALE :
           - Témoignages (placeholders)
           - Chiffres clés
           - Logos clients (placeholder)
        
        6. FAQ :
           - 5-7 questions fréquentes avec réponses
        
        7. CTA FINAL :
           - Dernier appel à l'action urgent
           - Garantie/rassurance
        
        Optimisation SEO :
        - Utilise naturellement les mots-clés
        - Structure hiérarchique H1/H2/H3
        - Meta title et description suggérés
        
        Rédige en français, contenu prêt à intégrer.";
        
        $content = $this->generateWithAI($prompt);
        
        return [
            'success' => true,
            'type' => 'page_web',
            'content' => $content,
            'metadata' => [
                'type_page' => $type,
                'sujet' => $sujet,
                'objectif' => $objectif,
                'ton' => $ton,
                'mots_cles' => $motsCles
            ]
        ];
    }
    
    /**
     * Génère une newsletter
     */
    private function generateNewsletter($requirements) {
        $sujet = $requirements['sujet'] ?? '';
        $ton = $requirements['ton'] ?? 'amicale';
        $segment = $requirements['segment'] ?? 'general';
        
        $prompt = "Tu es un expert en email marketing et newsletters.
        
        Écris une NEWSLETTER COMPLÈTE et ENGAGEANTE avec :
        - Sujet principal : {$sujet}
        - Ton : {$ton}
        - Segment d'audience : {$segment}
        
        Structure :
        1. OBJET DE L'EMAIL (5 variantes A/B test)
        2. PREHEADER TEXT (texte d'aperçu)
        3. SALUTATION PERSONNALISÉE
        4. INTRODUCTION (accroche personnelle)
        5. CONTENU PRINCIPAL (valeur ajoutée, histoire, conseil)
        6. CALL-TO-ACTION (clair et unique)
        7. P.S. (élément souvent lu, rappel ou bonus)
        8. SIGNATURE
        9. LIEN DE DÉSINSCRIPTION (placeholder)
        
        Bonnes pratiques :
        - Longueur : 200-400 mots
        - Paragraphes courts
        - Ton conversationnel
        - Une seule idée principale
        - CTA visible et clair
        
        Rédige en français, prêt à envoyer.";
        
        $content = $this->generateWithAI($prompt);
        
        return [
            'success' => true,
            'type' => 'newsletter',
            'content' => $content,
            'metadata' => [
                'sujet' => $sujet,
                'ton' => $ton,
                'segment' => $segment
            ]
        ];
    }
    
    /**
     * Générateur de contenu générique
     */
    private function generateGenericContent($type, $requirements) {
        $prompt = "Tu es un rédacteur professionnel polyvalent.
        
        Écris un contenu de type '{$type}' avec ces exigences :
        " . json_encode($requirements, JSON_PRETTY_PRINT) . "
        
        Livre un contenu COMPLET, structuré et professionnel.
        Adapte le ton et le style au contexte.
        Rédige en français.";
        
        $content = $this->generateWithAI($prompt);
        
        return [
            'success' => true,
            'type' => $type,
            'content' => $content,
            'metadata' => $requirements
        ];
    }
    
    /**
     * Construit le prompt pour un article
     */
    private function buildArticlePrompt($sujet, $ton, $longueur, $motsCles) {
        $wordCounts = ['court' => 500, 'moyen' => 1000, 'long' => 2000];
        $words = $wordCounts[$longueur] ?? 1000;
        
        return "Tu es un rédacteur web expert SEO.
        
        Écris un ARTICLE DE BLOG COMPLET et OPTIMISÉ SEO avec :
        - Sujet : {$sujet}
        - Ton : {$ton}
        - Longueur : environ {$words} mots
        - Mots-clés principaux : " . implode(', ', $motsCles) . "
        
        Structure requise :
        1. TITRE OPTIMISÉ SEO (incluant mot-clé principal)
        2. INTRODUCTION (accroche + présentation du sujet + annonce du plan)
        3. SOMMAIRE (si article long)
        4. CORPS DE L'ARTICLE (structuré en H2 et H3)
           - Chaque section doit être approfondie
           - Exemples concrets
           - Données/chiffres si pertinent
        5. CONCLUSION (résumé + ouverture)
        6. CALL-TO-ACTION
        
        Optimisation SEO :
        - Densité de mots-clés naturelle (1-2%)
        - Balises H1/H2/H3 hiérarchisées
        - Maillage interne suggéré
        - Questions sémantiques liées au sujet
        
        Style :
        - Paragraphes courts (3-5 lignes)
        - Phrases claires et directes
        - Ton : {$ton}
        - Engageant et facile à lire
        
        Rédige en français, article complet et prêt à publier.";
    }
    
    /**
     * Construit le prompt pour les métadonnées SEO
     */
    private function buildSEOPrompt($content, $motsCles) {
        return "À partir de ce contenu :
        {$content}
        
        Et ces mots-clés : " . implode(', ', $motsCles) . "
        
        Génère UNIQUEMENT un JSON valide avec cette structure :
        {
            \"meta_title\": \"Titre SEO optimisé (50-60 caractères)\",
            \"meta_description\": \"Description SEO (150-160 caractères)\",
            \"og_title\": \"Titre Open Graph\",
            \"og_description\": \"Description Open Graph\",
            \"focus_keyword\": \"Mot-clé principal\",
            \"slug_suggere\": \"url-slug-optimize\",
            \"reading_time_minutes\": X,
            \"internal_links_suggestions\": [\"idée 1\", \"idée 2\"],
            \"image_alt_suggestion\": \"Texte alt pour image principale\"
        }
        
        Retourne seulement le JSON, pas de texte autour.";
    }
    
    /**
     * Appelle l'API Mistral pour générer du contenu
     */
    private function generateWithAI($prompt) {
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un assistant expert en rédaction de contenu. Tu livres toujours du contenu COMPLET et PRÊT À L\'EMPLOI, jamais des extraits. Tu rédiges en français sauf indication contraire. Tu optimises automatiquement pour le SEO quand c\'est pertinent.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $result = $this->mistral->chat($messages, 'mistral-medium-2508', [
            'temperature' => 0.7,
            'max_tokens' => 8192
        ]);
        
        return $result['success'] ? $result['content'] : '';
    }
    
    /**
     * Adapte le ton d'un contenu existant
     */
    public function adaptTone($content, $newTone) {
        $prompt = "Réécris ce contenu avec un ton '{$newTone}' :
        
        {$content}
        
        Garde le même fond et les mêmes informations, mais adapte :
        - Le vocabulaire
        - La structure des phrases
        - Le niveau de formalité
        - Les expressions utilisées
        
        Livre le contenu entier réécrit, pas juste des extraits.
        Rédige en français.";
        
        return $this->generateWithAI($prompt);
    }
}
