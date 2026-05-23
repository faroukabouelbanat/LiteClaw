// VoAnh - JavaScript pour l'interface de chat

let currentConversationId = null;
let isSending = false;

document.addEventListener('DOMContentLoaded', function() {
    const sendBtn = document.getElementById('send-btn');
    const messageInput = document.getElementById('message-input');
    
    if (sendBtn) {
        sendBtn.addEventListener('click', sendMessage);
    }
    
    if (messageInput) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
});

async function sendMessage() {
    if (isSending) return;
    
    const messageInput = document.getElementById('message-input');
    const modelSelect = document.getElementById('model-select');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    isSending = true;
    messageInput.disabled = true;
    
    // Afficher le message utilisateur
    addMessageToUI('user', message);
    messageInput.value = '';
    
    // Masquer l'écran de bienvenue
    document.getElementById('welcome-screen').style.display = 'none';
    document.getElementById('messages-area').style.display = 'block';
    
    // Afficher l'indicateur de chargement
    const loadingId = addLoadingIndicator();
    
    try {
        const response = await fetch('/voanh/api/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                model: modelSelect.value,
                conversation_id: currentConversationId
            })
        });
        
        const data = await response.json();
        
        // Retirer l'indicateur de chargement
        removeLoadingIndicator(loadingId);
        
        if (data.success) {
            addMessageToUI('assistant', data.content, data.model);
            currentConversationId = data.conversation_id;
        } else {
            addMessageToUI('assistant', 'Erreur: ' + (data.error || 'Une erreur est survenue'));
        }
    } catch (error) {
        removeLoadingIndicator(loadingId);
        addMessageToUI('assistant', 'Erreur de connexion: ' + error.message);
    }
    
    isSending = false;
    messageInput.disabled = false;
    messageInput.focus();
}

function addMessageToUI(role, content, model = null) {
    const messagesList = document.getElementById('messages-list');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${role}`;
    
    let metaHtml = '';
    if (model) {
        metaHtml = `<div class="message-meta">Modèle: ${model}</div>`;
    }
    
    messageDiv.innerHTML = `
        <div class="message-content">${escapeHtml(content)}</div>
        ${metaHtml}
    `;
    
    messagesList.appendChild(messageDiv);
    messagesList.scrollTop = messagesList.scrollHeight;
}

function addLoadingIndicator() {
    const messagesList = document.getElementById('messages-list');
    const id = 'loading-' + Date.now();
    
    const loadingDiv = document.createElement('div');
    loadingDiv.id = id;
    loadingDiv.className = 'message assistant';
    loadingDiv.innerHTML = '<div class="message-content">⏳ Réflexion en cours...</div>';
    
    messagesList.appendChild(loadingDiv);
    messagesList.scrollTop = messagesList.scrollHeight;
    
    return id;
}

function removeLoadingIndicator(id) {
    const element = document.getElementById(id);
    if (element) {
        element.remove();
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Templates rapides
const templates = {
    code: "Génère un code pour : ",
    analyze: "Analyse en détail : ",
    create: "Crée un contenu créatif sur : ",
    plan: "Planifie et décompose cette tâche : "
};

function useTemplate(type) {
    const input = document.getElementById('message-input');
    if (input && templates[type]) {
        input.value = templates[type];
        input.focus();
    }
}

function startNewChat() {
    currentConversationId = null;
    document.getElementById('welcome-screen').style.display = 'flex';
    document.getElementById('messages-area').style.display = 'none';
    document.getElementById('messages-list').innerHTML = '';
}
