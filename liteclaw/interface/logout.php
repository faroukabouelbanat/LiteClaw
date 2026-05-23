<?php
/**
 * LiteClaw - Déconnexion
 */

require_once __DIR__ . '/../config.php';
require_once CORE_PATH . '/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: ../index.php');
exit;
