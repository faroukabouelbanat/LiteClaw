# VoAnh - Portail PHP/SQLite avec Mistral AI

## 🦞 Présentation

VoAnh est une réécriture complète en PHP/SQLite du projet LiteClaw, conçue pour fonctionner sur des hébergements mutualisés (Hostinger, OVH, o2Switch). Il intègre les API Mistral AI avec rotation automatique entre 3 clés API.

## ✨ Fonctionnalités

- **Chat IA** avec streaming et historique des conversations
- **Sessions multiples** avec persistance SQLite
- **Agent autonome** avec outils système (commandes shell sécurisées)
- **Vision Agent** pour l'automatisation de l'interface graphique
- **Sub-Agents** pour le traitement parallèle de tâches
- **Tâches planifiées (Cron)** avec déclencheurs webhook
- **Heartbeat Monitor** pour les tâches périodiques
- **Subconscious Innovator** pour l'auto-amélioration
- **WhatsApp/Telegram/Slack Bridge** (via Node.js ou API Cloud)
- **Gestion des compétences (Skills)** téléchargeables
- **Authentification utilisateur** avec API Key Mistral personnelle

## 🔧 Configuration Requise

- PHP 8.0+
- Extension PDO SQLite activée
- Extension cURL activée
- Extension JSON activée
- Extension MBString activée

## 📁 Structure du Projet

```
voanh/
├── config.php           # Configuration principale
├── db.php               # Gestion de la base SQLite
├── index.php            # Point d'entrée principal
├── api.php              # API REST
├── chat.php             # Interface de chat
├── admin.php            # Panneau d'administration
├── login.php            # Connexion utilisateur
├── register.php         # Inscription utilisateur
├── logout.php           # Déconnexion
├── mistral.php          # Client API Mistral
├── agent.php            # Moteur de l'agent IA
├── tools.php            # Outils système
├── memory.php           # Gestion de la mémoire
├── scheduler.php        # Tâches planifiées
├── heartbeat.php        # Moniteur Heartbeat
├── subconscious.php     # Innovateur Subconscious
├── vision.php           # Agent Vision (simulation)
├── subagent.php         # Gestion des sub-agents
├── web_utils.php        # Utilitaires web
├── auth.php             # Authentification
├── styles.css           # Styles CSS
└── README.md            # Ce fichier
```

## 🚀 Installation

1. **Téléversez les fichiers** sur votre hébergement
2. **Assurez-vous que le dossier `data/`** a les permissions `0755`
3. **Accédez à `index.php`** dans votre navigateur
4. **Inscrivez-vous** avec votre email et mot de passe
5. **Ajoutez votre API Key Mistral** dans votre profil

## 🔑 Clés API Mistral

Le système utilise 3 clés API Mistral par défaut avec rotation automatique :

```php
define('DEFAULT_MISTRAL_API_KEYS', [
    '5qaRTjaH8Rake',
    'o3rG1zaShytu',
    'vEzQaFruXkF'
]);
```

Chaque clé offre 1 milliard de tokens par mois.

## 🤖 Modèles Disponibles

### Code & Développement
- `codestral-2508` - Auto-complétion en temps réel
- `devstral-2512` - Architectures logicielles
- `devstral-medium-2507` - Débogage quotidien
- `devstral-small-2507` - Tests unitaires rapides

### Raisonnement & Haute Performance
- `mistral-large-2512` - État de l'art, raisonnement logique
- `mistral-large-2411` - Version stable précédente

### Modèles Intermédiaires
- `mistral-medium-2508` - Tâches administratives complexes
- `mistral-medium-2505` - Bases de connaissances RAG

### Vitesse & Automatisation
- `mistral-small-2603` - Extraction de données de masse
- `mistral-small-2506` - Classification et tagging

### Agents Spécialisés
- `magistral-medium-2509` - Orchestration multi-agents
- `magistral-small-2509` - Routage rapide

### Créativité
- `labs-mistral-small-creative` - Storytelling et brainstorming

### Vision
- `pixtral-large-2411` - Analyse d'images complexes
- `pixtral-12b-2409` - OCR rapide

### Edge Computing
- `ministral-14b-2512` - Plus puissant modèle compact
- `ministral-8b-2512` - Applications mobiles
- `ministral-3b-2512` - Ultra-léger

### Audio
- `voxtral-small-2507` - Analyse sémantique audio
- `voxtral-mini-2507` - Traitement rapide flux audio

## 📝 Utilisation

### Chat Simple
```php
// Via API
POST /api.php
{
    "action": "chat",
    "message": "Bonjour!",
    "session_id": "default",
    "model": "mistral-medium-2508"
}
```

### Création de Session
```php
POST /api.php
{
    "action": "create_session",
    "session_id": "ma_session"
}
```

### Tâche Planifiée
```php
POST /api.php
{
    "action": "create_cron_job",
    "name": "Rapport quotidien",
    "schedule_type": "cron",
    "schedule_value": "0 9 * * *",
    "task": "Génère un rapport des activités"
}
```

## 🔒 Sécurité

- Les commandes shell dangereuses sont bloquées
- Protection contre l'injection SQL via PDO préparé
- Hachage des mots de passe avec password_hash()
- Protection CSRF sur les formulaires
- Limitation des permissions de fichiers (0755/0644)

## 🛠️ Restrictions Hostinger

Ce code respecte les limitations des hébergements mutualisés :

- ❌ Pas de `file_get_contents()` pour URLs externes → utilise cURL
- ❌ Pas de `exec()`, `shell_exec()`, `system()` → commandes limitées
- ❌ Pas de chemins absolus → chemins relatifs avec `__DIR__`
- ❌ Pas de `set_time_limit()` excessif → timeout raisonnable
- ❌ Pas de permissions 0777 → 0755 pour dossiers, 0644 pour fichiers

## 📊 Base de Données

La base SQLite contient les tables suivantes :

- `users` - Utilisateurs inscrits
- `sessions` - Sessions de conversation
- `messages` - Historique des messages
- `cron_jobs` - Tâches planifiées
- `sub_agents` - Sub-agents actifs
- `user_api_keys` - Clés API personnelles

## 🔄 Rotation API Keys

Le système rotate automatiquement entre les 3 clés API :

1. Utilise la clé actuelle jusqu'à épuisement ou erreur
2. Bascule sur la clé suivante en cas d'erreur 429 (rate limit)
3. Retourne à la première clé après un cycle complet

## 🎯 Personnalisation

### Ajouter un Modèle
Modifiez le tableau `$MODELS` dans `config.php`

### Changer les Clés API
Modifiez `DEFAULT_MISTRAL_API_KEYS` dans `config.php`

### Personnaliser le Prompt Système
Éditez `config/SOUL.md`, `config/PERSONALITY.md`, etc.

## 📞 Support

Pour toute question ou problème, ouvrez une issue sur le dépôt.

## 📄 Licence

MIT License
