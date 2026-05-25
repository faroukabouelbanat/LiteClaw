<?php
/**
 * LiteClaw Memory - PHP Version
 * Gère la mémoire des sessions et messages
 */

require_once __DIR__ . '/db.php';

/**
 * Create a session (alias for createSession in db.php)
 */
function createMemorySession(string $sessionId, ?string $parentSessionId = null): bool {
    return createSession($sessionId, $parentSessionId);
}

/**
 * List sessions (alias for listSessions in db.php)
 */
function listMemorySessions(): array {
    return listSessions();
}

/**
 * Add message to memory (alias for addMessage in db.php)
 */
function addMemoryMessage(string $sessionId, array $message): void {
    addMessage($sessionId, $message);
}

/**
 * Get session history (alias for getSessionHistory in db.php)
 */
function getSessionMemory(string $sessionId, int $limit = 20): array {
    return getSessionHistory($sessionId, $limit);
}

/**
 * Reset session (alias for resetSession in db.php)
 */
function resetMemorySession(string $sessionId): bool {
    return resetSession($sessionId);
}
