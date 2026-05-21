<?php
/**
 * VoAnh - Déconnexion Utilisateur
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Déconnecter l'utilisateur
logoutUser();

// Rediriger vers la page d'accueil
redirect('index.php');
