/**
 * VoAnh - Scripts JavaScript Principaux
 */

// État global
let currentSessionId = '';
let isStreaming = false;
let abortController = null;

// Charger la liste des sessions
async function loadSessions() {
    try {
        const response = await fetch('api.php?action=list_sessions', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderSessionsList(data.sessions);
        } else {
            console.error('Erreur chargement sessions:', data);
        }
    } catch (error) {
        console.error('Erreur:', error);
        document.getElementById('sessionsList').innerHTML = 
            '<div class="error">Erreur de chargement</div>';
    }
}

// Afficher la liste des sessions
function renderSessionsList(sessions) {
    const container = document.getElementById('sessionsList');
    
    if (!sessions || sessions.length === 0) {
        container.innerHTML = '<div class="no-sessions">Aucune session</div>';
        return;
    }
    
    let html = '';
    sessions.forEach(session => {
        const isActive = session.session_id === currentSessionId;
        const date = new Date(session.created_at).toLocaleDateString('fr-FR');
        
        html += `
            <div class="session-item ${isActive ? 'active' : ''}" 
                 data-session-id="${escapeHtml(session.session_id)}"
                 onclick="switchSession('${escapeHtml(session.session_id)}')">
                <span class="session-name">${escapeHtml(session.session_id)}</span>
                <span class="session-date">${date}</span>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Changer de session
function switchSession(sessionId) {
    currentSessionId = sessionId;
    document.getElementById('sessionId').value = sessionId;
    
    // Mettre à jour l'affichage
    document.querySelectorAll('.session-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.sessionId === sessionId) {
            item.classList.add('active');
        }
    });
    
    // Charger l'historique
    loadHistory(sessionId);
}

// Charger l'historique d'une session
async function loadHistory(sessionId) {
    try {
        const response = await fetch('api.php?action=get_history', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: sessionId,
                limit: 50,
                csrf_token: window.csrfToken || ''
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderMessages(data.history);
        }
    } catch (error) {
        console.error('Erreur chargement historique:', error);
    }
}

// Afficher les messages
function renderMessages(messages) {
    const container = document.getElementById('messagesContainer');
    
    if (!messages || messages.length === 0) {
        // Garder le message de bienvenue
        return;
    }
    
    // Effacer le message de bienvenue
    container.querySelector('.welcome-message')?.remove();
    
    let html = '';
    messages.forEach(msg => {
        const roleClass = msg.role === 'user' ? 'user-message' : 
                         (msg.role === 'tool' ? 'tool-message' : 'assistant-message');
        const icon = msg.role === 'user' ? '👤' : 
                    (msg.role === 'tool' ? '🔧' : '🦞');
        
        html += `
            <div class="message ${roleClass}">
                <div class="message-header">
                    <span class="message-icon">${icon}</span>
                    <span class="message-role">${translateRole(msg.role)}</span>
                    ${msg.model_used ? `<span class="model-badge">${escapeHtml(msg.model_used)}</span>` : ''}
                </div>
                <div class="message-content">${formatMessageContent(msg.content)}</div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    scrollToBottom();
}

// Traduire le rôle
function translateRole(role) {
    const translations = {
        'user': 'Vous',
        'assistant': 'VoAnh',
        'tool': 'Outil',
        'system': 'Système'
    };
    return translations[role] || role;
}

// Formater le contenu du message (Markdown basique)
function formatMessageContent(content) {
    if (!content) return '';
    
    // Échapper le HTML
    let formatted = escapeHtml(content);
    
    // Code blocks
    formatted = formatted.replace(/```(\w*)\n([\s\S]*?)```/g, 
        '<pre><code class="language-$1">$2</code></pre>');
    
    // Inline code
    formatted = formatted.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Bold
    formatted = formatted.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    
    // Italic
    formatted = formatted.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    
    // Line breaks
    formatted = formatted.replace(/\n/g, '<br>');
    
    return formatted;
}

// Envoyer un message
async function sendMessage(message) {
    const sessionId = document.getElementById('sessionId').value;
    const model = document.getElementById('modelSelect').value;
    
    if (!message.trim()) return;
    
    // Ajouter le message utilisateur immédiatement
    appendMessage('user', message);
    
    // Effacer le champ
    document.getElementById('messageInput').value = '';
    
    // Afficher l'indicateur de frappe
    showTypingIndicator();
    
    try {
        const response = await fetch('api.php?action=chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                session_id: sessionId,
                model: model,
                csrf_token: window.csrfToken || ''
            })
        });
        
        const data = await response.json();
        
        // Masquer l'indicateur de frappe
        hideTypingIndicator();
        
        if (data.success) {
            appendMessage('assistant', data.response);
            
            // Mettre à jour l'utilisation des tokens si disponible
            if (data.usage) {
                updateTokenUsage(data.usage);
            }
        } else {
            appendMessage('assistant', '❌ Erreur: ' + (data.error || 'Erreur inconnue'));
        }
    } catch (error) {
        hideTypingIndicator();
        appendMessage('assistant', '❌ Erreur de connexion: ' + error.message);
    }
}

// Ajouter un message à l'affichage
function appendMessage(role, content) {
    const container = document.getElementById('messagesContainer');
    
    // Retirer le message de bienvenue si présent
    container.querySelector('.welcome-message')?.remove();
    
    const icon = role === 'user' ? '👤' : (role === 'tool' ? '🔧' : '🦞');
    const roleClass = role === 'user' ? 'user-message' : 
                     (role === 'tool' ? 'tool-message' : 'assistant-message');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${roleClass}`;
    messageDiv.innerHTML = `
        <div class="message-header">
            <span class="message-icon">${icon}</span>
            <span class="message-role">${translateRole(role)}</span>
        </div>
        <div class="message-content">${formatMessageContent(content)}</div>
    `;
    
    container.appendChild(messageDiv);
    scrollToBottom();
}

// Afficher l'indicateur de frappe
function showTypingIndicator() {
    const container = document.getElementById('messagesContainer');
    
    const typingDiv = document.createElement('div');
    typingDiv.id = 'typingIndicator';
    typingDiv.className = 'message assistant-message typing';
    typingDiv.innerHTML = `
        <div class="message-header">
            <span class="message-icon">🦞</span>
            <span class="message-role">VoAnh</span>
        </div>
        <div class="message-content">
            <div class="typing-dots">
                <span></span><span></span><span></span>
            </div>
        </div>
    `;
    
    container.appendChild(typingDiv);
    scrollToBottom();
}

// Masquer l'indicateur de frappe
function hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    indicator?.remove();
}

// Faire défiler vers le bas
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    container.scrollTop = container.scrollHeight;
}

// Créer une nouvelle session
async function createNewSession() {
    const newSessionId = 'session_' + Date.now();
    
    try {
        const response = await fetch('api.php?action=create_session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: newSessionId,
                csrf_token: window.csrfToken || ''
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentSessionId = newSessionId;
            document.getElementById('sessionId').value = newSessionId;
            
            // Recharger la liste des sessions
            loadSessions();
            
            // Effacer l'affichage des messages
            const container = document.getElementById('messagesContainer');
            container.innerHTML = `
                <div class="welcome-message">
                    <h2>Nouvelle session 🎉</h2>
                    <p>Commencez à discuter dans cette nouvelle session !</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erreur création session:', error);
    }
}

// Réinitialiser la session actuelle
async function resetCurrentSession() {
    const sessionId = document.getElementById('sessionId').value;
    
    if (!sessionId) {
        alert('Aucune session active');
        return;
    }
    
    if (!confirm('Voulez-vous vraiment effacer l\'historique de cette session ?')) {
        return;
    }
    
    try {
        const response = await fetch('api.php?action=reset_session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: sessionId,
                csrf_token: window.csrfToken || ''
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Effacer l'affichage
            const container = document.getElementById('messagesContainer');
            container.innerHTML = `
                <div class="welcome-message">
                    <h2>Session réinitialisée 🔄</h2>
                    <p>L\'historique a été effacé.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erreur réinitialisation:', error);
    }
}

// Supprimer une session
async function deleteSession(sessionId) {
    if (!confirm('Supprimer cette session définitivement ?')) {
        return;
    }
    
    try {
        const response = await fetch('api.php?action=delete_session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                session_id: sessionId,
                csrf_token: window.csrfToken || ''
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Si c'est la session actuelle, en créer une nouvelle
            if (sessionId === currentSessionId) {
                createNewSession();
            }
            
            // Recharger la liste
            loadSessions();
        }
    } catch (error) {
        console.error('Erreur suppression:', error);
    }
}

// Mettre à jour l'affichage des tokens
function updateTokenUsage(usage) {
    const usageEl = document.getElementById('tokenUsage');
    if (usageEl && usage) {
        usageEl.textContent = `Tokens: ${usage.prompt_tokens || 0} + ${usage.completion_tokens || 0} = ${usage.total_tokens || 0}`;
    }
}

// Échapper le HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Gestionnaires d'événements
document.addEventListener('DOMContentLoaded', function() {
    // Formulaire de chat
    const chatForm = document.getElementById('chatForm');
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = document.getElementById('messageInput').value;
        if (message.trim()) {
            sendMessage(message);
        }
    });
    
    // Bouton Nouvelle Session
    document.getElementById('newSessionBtn').addEventListener('click', createNewSession);
    
    // Bouton Effacer
    document.getElementById('clearBtn').addEventListener('click', resetCurrentSession);
    
    // Raccourci clavier (Ctrl+Entrée pour envoyer)
    document.getElementById('messageInput').addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
});
