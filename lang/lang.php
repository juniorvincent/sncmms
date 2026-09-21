<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Définir la langue par défaut (Français) ou récupérer celle choisie
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fr';
}

$lang = $_SESSION['lang'];

// Dictionnaire des traductions
$translations = [
    'fr' => [
        'title' => 'Gestion de Maintenance Réseau & Informatique',
        'dashboard' => 'Tableau de bord',
        'tickets' => 'Tickets de maintenance',
        'assets' => 'Équipements',
        'logout' => 'Déconnexion',
        'welcome' => 'Bienvenue sur SNCMMS',
    ],
    'en' => [
        'title' => 'Smart Network & Computer Maintenance Management',
        'dashboard' => 'Dashboard',
        'tickets' => 'Maintenance Tickets',
        'assets' => 'Assets',
        'logout' => 'Logout',
        'welcome' => 'Welcome to SNCMMS',
    ]
];

// Fonction pour afficher le texte traduit
function __($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}
?>