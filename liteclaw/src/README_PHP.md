# LiteClaw PHP - Documentation

## 📁 Structure du projet

```
liteclaw/
├── src/                  # Nouveau dossier avec tous les fichiers PHP convertis
│   ├── config.php        # Configuration et paramètres
│   ├── db.php            # Base de données SQLite
│   ├── memory.php        # Gestion des sessions et messages
│   ├── meta_memory.php   # Mémoire persistante (SOUL, PERSONALITY, etc.)
│   ├── tools.php         # Outils système et commandes shell
│   ├── llm.php           # Interface avec les API LLM
│   ├── agent.php         # Cœur de l'agent IA
│   └── main.php          # API principale avec interface web
├── core/                 # Anciens fichiers PHP (site_generator, etc.)
├── api/                  # API endpoints
├── interface/            # Fichiers d'interface utilisateur
├── index.php             # Point d'entrée principal
└── config.php            # Configuration globale
```

## 🔄 Fichiers Python convertis en PHP

| Fichier Python | Fichier PHP | Description |
|----------------|-------------|-------------|
| `config.py` | `src/config.php` | Configuration et paramètres |
| `db.py` | `src/db.php` | Base de données SQLite |
| `memory.py` | `src/memory.php` | Gestion des sessions/messages |
| `meta_memory.py` | `src/meta_memory.php` | Mémoire persistante |
| `tools.py` | `src/tools.php` | Commandes shell sécurisées |
| `llm.py` | `src/llm.php` | Interface API LLM |
| `agent.py` | `src/agent.php` | Agent IA principal |
| `main.py` | `src/main.php` | API + Interface web |

## 🚀 Installation

### Prérequis
- PHP 7.4 ou supérieur
- Extension PDO SQLite activée
- Extension cURL activée
- Serveur web (Apache, Nginx) ou PHP built-in server

### Démarrage rapide

```bash
# Avec le serveur intégré PHP
cd /workspace/liteclaw/src
php -S localhost:8080

# Ou pointer vers le dossier liteclaw
cd /workspace/liteclaw
php -S localhost:8080
```

### Configuration

1. Copiez `config.json.example` vers `config.json`
2. Éditez avec vos clés API :

```json
{
    "LLM_PROVIDER": "openai",
    "LLM_API_KEY": "votre-clé-api",
    "LLM_MODEL": "gpt-4o",
    "WHATSAPP_ALLOWED_NUMBERS": ["+1234567890"]
}
```

## 📡 Endpoints API

### POST `/chat`
Envoyer un message à l'agent

```json
{
    "message": "Bonjour!",
    "session_id": "default",
    "stream": false
}
```

### POST `/session/create`
Créer une nouvelle session

```json
{
    "session_id": "ma-session"
}
```

### GET `/sessions/list`
Lister toutes les sessions

### POST `/reset`
Réinitialiser une session

```json
{
    "session_id": "default"
}
```

## 🌐 Interface Web

Accédez à `http://localhost:8080/src/main.php` pour l'interface de chat complète avec :
- 💬 Chat en temps réel
- 🔄 Gestion des sessions
- 🔴 Reset de conversation
- 📱 Design responsive

## 🔒 Sécurité

### Commandes bloquées
Le système bloque automatiquement les commandes dangereuses :
- Auto-destruction (`taskkill python`, `kill node`, etc.)
- Destruction système (`rm -rf /`, `format c:`, etc.)
- Corruption registre
- Attaques réseau

### Validation des sessions
- Sessions isolées par utilisateur
- Pas d'exécution de code arbitraire
- Protection contre les injections SQL (requêtes préparées)

## 🧠 Mémoire

### Types de mémoire
1. **SOUL** : Faits et préférences utilisateur
2. **PERSONALITY** : État et règles internes de l'IA
3. **SUBCONSCIOUS** : Idées innovantes et patterns d'erreur
4. **LEARNING** : Meilleures pratiques et workflows

### Stockage
- Messages : SQLite (`liteclaw_memory.db`)
- Méta-mémoire : Fichiers Markdown dans `WORK_DIR/configs/`

## ⚙️ Variables d'environnement

Ou utilisez `.env` :

```env
LLM_PROVIDER=openai
LLM_API_KEY=sk-...
LLM_MODEL=gpt-4o
WHATSAPP_ALLOWED_NUMBERS=+1234567890,+0987654321
GIPHY_API_KEY=votre-clé-giphy
AWS_REGION_NAME=us-east-1
```

## 🛠️ Différences avec la version Python

### Limitations
- ❌ Streaming SSE non supporté nativement en PHP
- ❌ Sub-agents multiples (nécessiterait des processus séparés)
- ❌ Vision Agent (nécessite Selenium/Playwright)
- ❌ WhatsApp/Selenium bridge (Node.js requis)
- ❌ Cron jobs (nécessite un daemon externe)

### Avantages
- ✅ Plus simple à déployer (PHP omniprésent)
- ✅ Interface web intégrée
- ✅ Pas de dépendances complexes (pip, npm)
- ✅ Compatible avec tous les hébergements mutualisés

## 📝 Notes importantes

1. **Base de données** : Le fichier SQLite est créé automatiquement dans `WORK_DIR`
2. **Permissions** : Assurez-vous que PHP peut écrire dans `WORK_DIR`
3. **API Keys** : Ne commitez jamais `config.json` avec vos vraies clés
4. **Production** : Utilisez HTTPS et authentification supplémentaire

## 🔮 Fonctionnalités futures (à implémenter)

- [ ] Support du streaming avec Server-Sent Events
- [ ] Intégration WhatsApp via webhook
- [ ] Système de cron jobs avec `pcntl_alarm`
- [ ] Vision Agent avec bibliothèque d'automatisation
- [ ] Sub-agents avec pthreads ou extensions parallèles

---

**LiteClaw PHP** - Votre assistant AGI entièrement en PHP ! 🤖
