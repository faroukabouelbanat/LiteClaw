# LiteClaw - Version PHP

LiteClaw est un agent IA autonome écrit en PHP qui peut interagir avec votre ordinateur, naviguer sur le web, et exécuter des tâches complexes.

## Structure du projet

```
liteclaw/
├── src/                  # Code source principal (PHP)
│   ├── config.php        # Configuration et paramètres
│   ├── db.php            # Gestion de la base de données SQLite
│   ├── memory.php        # Mémoire des sessions et messages
│   ├── meta_memory.php   # Mémoire persistante (SOUL, PERSONALITY, etc.)
│   ├── tools.php         # Outils système et commandes shell
│   ├── llm.php           # Interface avec les modèles de langage
│   ├── agent.php         # Cœur de l'agent IA
│   └── main.php          # API principale et point d'entrée
├── interface/            # Interface utilisateur web
│   ├── index.php         # Page principale
│   ├── login.php         # Authentification
│   └── register.php      # Inscription
├── api/                  # Endpoints API
│   └── chat.php          # Endpoint de chat
├── core/                 # Modules principaux
│   ├── auth.php          # Authentification
│   ├── database.php      # Base de données
│   ├── mistral.php       # Intégration Mistral AI
│   ├── content_generator.php
│   ├── image_generator.php
│   └── video_generator.php
└── storage/              # Fichiers temporaires et uploads
```

## Installation

### Prérequis

- PHP 8.0 ou supérieur
- Extension PDO SQLite activée
- Extension cURL activée
- Extension JSON activée

### Étapes

1. Clonez le dépôt :
```bash
git clone https://github.com/faroukabouelbanat/LiteClaw.git
cd liteclaw
```

2. Configurez les variables d'environnement dans `.env` :
```
LITECLAW_WORK_DIR=/chemin/vers/liteclaw/data
LLM_PROVIDER=openai
LLM_API_KEY=votre_cle_api
LLM_MODEL=gpt-4o
```

3. Assurez-vous que le dossier `storage` est accessible en écriture :
```bash
chmod -R 755 storage
```

4. Lancez le serveur PHP intégré :
```bash
php -S localhost:8000 -t .
```

5. Accédez à l'interface : http://localhost:8000

## Utilisation

### Via l'interface web

Accédez à `http://localhost:8000` et interagissez avec LiteClaw via le chat.

### Via l'API

```bash
# Créer une session
curl -X POST "http://localhost:8000/src/main.php?action=create_session" \
  -H "Content-Type: application/json"

# Envoyer un message
curl -X POST "http://localhost:8000/src/main.php?action=chat" \
  -H "Content-Type: application/json" \
  -d '{"message": "Bonjour!", "session_id": "default"}'
```

## Fonctionnalités

- **Agent IA Autonome** : LiteClaw peut exécuter des tâches complexes de manière autonome
- **Mémoire Persistante** : SOUL, PERSONALITY, SUBCONSCIOUS pour l'évolution continue
- **Outils Système** : Exécution de commandes shell sécurisées
- **Navigation Web** : Interaction avec les navigateurs et applications desktop
- **Multi-Sessions** : Gestion de plusieurs conversations simultanées
- **Interface Web** : Interface utilisateur intuitive et responsive

## Sécurité

LiteClaw inclut une couche de sécurité qui bloque les commandes dangereuses :
- Auto-destruction (kill python, taskkill, etc.)
- Destruction système (rm -rf /, format, etc.)
- Corruption du registre
- Attaques réseau

## Architecture PHP

Tous les modules sont écrits en PHP natif sans dépendances externes lourdes :
- **PDO SQLite** pour la base de données
- **cURL** pour les appels API
- **Sessions PHP** pour la gestion d'état
- **JSON** pour l'échange de données

## Différences avec la version Python

Cette version PHP offre :
- ✅ Aucune dépendance Python complexe
- ✅ Déploiement simplifié (uniquement PHP)
- ✅ Compatibilité avec tous les hébergeurs web
- ✅ Performance équivalente pour les tâches IA
- ✅ Même fonctionnalités que la version originale

## Licence

MIT License - Voir LICENSE pour plus de détails.

## Support

Pour toute question ou problème, ouvrez une issue sur GitHub.
