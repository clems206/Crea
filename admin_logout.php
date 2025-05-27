<?php
// admin_logout.php
session_start(); // Démarrer la session pour pouvoir y accéder

// 1. Effacer toutes les variables de session.
$_SESSION = array();

// 2. Si vous souhaitez détruire complètement la session, effacez également le cookie de session.
// Note : cela détruira la session et pas seulement les données de session !
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Mettre un temps d'expiration dans le passé
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finalement, détruire la session.
session_destroy();

// 4. Rediriger vers la page de connexion.
// Vous pouvez ajouter un paramètre GET pour afficher un message sur la page de connexion.
header('Location: admin_login.php?status=logged_out');
exit; // Assurez-vous qu'aucun autre code n'est exécuté après la redirection.
?>
