# LiteClaw - Clone de Claude AI en PHP

LiteClaw est un clone de Claude AI développé en PHP, utilisant l'API Mistral AI avec 20 modèles différents et une sélection intelligente automatique.

## 🚀 Fonctionnalités

- **20 Modèles Mistral** : Code, Vision, Agent, Creative, Edge, Audio, Flagship, Medium, Small
- **Sélection Automatique Intelligente** : L'IA choisit le meilleur modèle selon votre tâche
- **3 Clés API avec Rotation** : 1 milliard de tokens/mois pour chaque clé
- **Interface Style Claude** : Design moderne et sombre similaire à Claude AI
- **Compatible Hostinger** : Optimisé pour hébergement mutualisé
- **Base de Données SQLite** : Pas de configuration MySQL requise
- **Authentification Complète** : Inscription, connexion, gestion de session
- **Historique des Conversations** : Sauvegarde automatique des discussions

## 📁 Structure du Projet

```
liteclaw/
├── index.php           # Page d'accueil (racine)
├── config.php          # Configuration principale
├── api/
│   └── chat.php        # API de chat
├── core/
│   ├── database.php    # Gestion SQLite
│   ├── auth.php        # Authentification
│   └── mistral.php     # Client API Mistral
├── interface/
│   ├── login.php       # Page de connexion
│   ├── register.php    # Page d'inscription
│   └── logout.php      # Déconnexion
├── assets/
│   ├── styles.css      # Styles CSS
│   └── script.js       # JavaScript
├── data/               # Base de données SQLite
└── logs/               # Fichiers de log
```

## 🔧 Installation

### 1. Téléchargement

Copiez tous les fichiers dans votre répertoire racine sur Hostinger ou votre serveur.

### 2. Permissions

Assurez-vous que les dossiers suivants ont les permissions 0755 :
- `data/`
- `logs/`
- `sandbox/`

### 3. Configuration

Les clés API Mistral sont déjà configurées dans `config.php` :

```php
define('DEFAULT_MISTRAL_API_KEYS', [
    '5qaRTjH8Rake',
    'o3rG1RShytu',
    'vEzQMKDjFruXkF'
]);
```

### 4. Accès

Ouvrez simplement votre navigateur et accédez à :
```
https://votre-domaine.com/
```

## 🎯 Sélection Automatique de Modèle

LiteClaw analyse votre demande et choisit automatiquement le meilleur modèle :

| Type de tâche | Modèle sélectionné |
|--------------|-------------------|
| Code/Développement | devstral-2512 |
| Debug/Correction | devstral-medium-2507 |
| Analyse/Raisonnement | mistral-large-2512 |
| Tâche rapide | mistral-small-2603 |
| Créativité | labs-mistral-small-creative |
| Planification | magistral-medium-2509 |

Vous pouvez aussi sélectionner manuellement un modèle dans la liste déroulante.

## 📋 Modèles Disponibles

### Code & Développement
- codestral-2508 : Auto-complétion temps réel
- devstral-2512 : Architecture, déploiement
- devstral-medium-2507 : Débogage quotidien
- devstral-small-2507 : Tests unitaires, CI/CD

### Flagship (Raisonnement)
- mistral-large-2512 : État de l'art, contexte massif
- mistral-large-2411 : Version stable entreprise

### Medium (Business)
- mistral-medium-2508 : Tâches admin complexes
- mistral-medium-2505 : RAG, synthèse documents

### Small (Rapide)
- mistral-small-2603 : Extraction de masse
- mistral-small-2506 : Classification, tagging

### Agent (Orchestration)
- magistral-medium-2509 : Multi-agents
- magistral-small-2509 : Routage rapide

### Vision (Images)
- pixtral-large-2411 : Analyse UI, plans
- pixtral-12b-2409 : OCR rapide

### Creative
- labs-mistral-small-creative : Storytelling

### Edge (Compact)
- ministral-14b-2512 : Modèle compact puissant
- ministral-8b-2512 : Mobile
- ministral-3b-2512 : Ultra-léger

### Audio
- voxtral-small-2507 : Analyse audio
- voxtral-mini-2507 : Flux rapide

## ⚙️ Spécifications Serveur Requises

- **PHP 7.4+** (PHP 8.x recommandé)
- **Extension PDO SQLite** activée
- **Extension cURL** activée
- **Extension JSON** activée
- **Espace disque** : ~10MB minimum

## 🔒 Sécurité

- Mots de passe hashés avec `password_hash()`
- Sessions sécurisées avec tokens
- Protection contre les attaques par force brute
- Logs d'activité pour audit
- Pas d'affichage d'erreurs brutes en production

## 📝 Notes pour Hostinger Mutualisé

Ce code est spécifiquement optimisé pour les hébergements mutualisés :

✅ **Utilise cURL** pour les requêtes HTTP (pas `file_get_contents`)  
✅ **Pas de fonctions système** (`exec`, `shell_exec` désactivés)  
✅ **Chemins relatifs** (pas de chemins absolus codés en dur)  
✅ **Permissions 0755** (pas de 0777)  
✅ **SQLite** (pas de MySQL externe requis)  
✅ **Gestion mémoire optimisée**  

❌ **Ne pas utiliser** : `exec()`, `shell_exec()`, `system()`, `putenv()`

## 🆘 Support

En cas de problème, consultez les logs dans `logs/liteclaw.log`.

## 📄 Licence

Projet open source pour usage personnel et éducatif.
