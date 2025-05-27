<?php
// admin_settings.php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'includes/db_config.php';
$pdo = connect_db();

$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$feedback_message = '';
$feedback_type = ''; // 'success' ou 'error'

// --- Fonctions pour gérer les paramètres en BDD ---
function get_setting($pdo_conn, $setting_name, $default_value = null) {
    if (!$pdo_conn) return $default_value;
    try {
        $stmt = $pdo_conn->prepare("SELECT setting_value FROM site_settings WHERE setting_name = :setting_name");
        $stmt->bindParam(':setting_name', $setting_name);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return ($result !== false) ? $result : $default_value;
    } catch (PDOException $e) {
        error_log("Erreur get_setting ($setting_name): " . $e->getMessage());
        return $default_value;
    }
}

function update_setting($pdo_conn, $setting_name, $setting_value) {
    if (!$pdo_conn) return false;
    try {
        // Utiliser INSERT ... ON DUPLICATE KEY UPDATE pour insérer si la clé n'existe pas, ou mettre à jour si elle existe.
        // Nécessite que setting_name soit une PRIMARY KEY ou UNIQUE KEY.
        $sql = "INSERT INTO site_settings (setting_name, setting_value) 
                VALUES (:setting_name, :setting_value)
                ON DUPLICATE KEY UPDATE setting_value = :setting_value_update";
        $stmt = $pdo_conn->prepare($sql);
        $stmt->bindParam(':setting_name', $setting_name);
        $stmt->bindParam(':setting_value', $setting_value);
        $stmt->bindParam(':setting_value_update', $setting_value); // Même valeur pour la mise à jour
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur update_setting ($setting_name): " . $e->getMessage());
        return false;
    }
}

// --- Récupération des paramètres actuels depuis la BDD ---
// Les valeurs par défaut ici sont utilisées si le paramètre n'est pas encore dans la BDD
// ou si la connexion BDD échoue.
$current_settings = [
    'site_contact_email' => get_setting($pdo, 'site_contact_email', 'contact@creamod3d.fr'),
    'facebook_url' => get_setting($pdo, 'facebook_url', 'https://www.facebook.com/profile.php?id=61576107364762'),
    'instagram_url' => get_setting($pdo, 'instagram_url', 'https://www.instagram.com/creamod3d.fr/'),
    'maintenance_mode' => (bool)get_setting($pdo, 'maintenance_mode', '0'), // Convertir en booléen
    'items_per_page_shop' => (int)get_setting($pdo, 'items_per_page_shop', '12') // Convertir en entier
];


// Traitement du formulaire de sauvegarde des paramètres
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $new_values_from_post = [
        'site_contact_email' => filter_var(trim($_POST['site_contact_email'] ?? ''), FILTER_SANITIZE_EMAIL),
        'facebook_url' => filter_var(trim($_POST['facebook_url'] ?? ''), FILTER_SANITIZE_URL),
        'instagram_url' => filter_var(trim($_POST['instagram_url'] ?? ''), FILTER_SANITIZE_URL),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0', // Stocker '1' ou '0' pour la BDD
        'items_per_page_shop' => filter_var(trim($_POST['items_per_page_shop'] ?? '12'), FILTER_VALIDATE_INT, ['options' => ['default' => 12, 'min_range' => 1]])
    ];

    $errors = [];
    if (empty($new_values_from_post['site_contact_email']) || !filter_var($new_values_from_post['site_contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email de contact du site est invalide.";
    }
    // Autoriser les URL vides, mais valider si non vides
    if (!empty($new_values_from_post['facebook_url']) && !filter_var($new_values_from_post['facebook_url'], FILTER_VALIDATE_URL)) {
        $errors[] = "L'URL Facebook est invalide.";
    }
    if (!empty($new_values_from_post['instagram_url']) && !filter_var($new_values_from_post['instagram_url'], FILTER_VALIDATE_URL)) {
        $errors[] = "L'URL Instagram est invalide.";
    }

    if (empty($errors)) {
        $all_saved_successfully = true;
        if ($pdo) { // S'assurer que la connexion BDD est active
            foreach ($new_values_from_post as $name => $value) {
                if (!update_setting($pdo, $name, $value)) {
                    $all_saved_successfully = false;
                    error_log("Échec de la mise à jour du paramètre : $name");
                }
            }
        } else {
            $all_saved_successfully = false;
            $feedback_message = "Erreur de connexion à la base de données. Les paramètres n'ont pas pu être sauvegardés.";
            $feedback_type = 'error';
        }


        if ($all_saved_successfully) {
            // Recharger les paramètres depuis la BDD pour refléter les changements
            $current_settings = [
                'site_contact_email' => get_setting($pdo, 'site_contact_email', 'contact@creamod3d.fr'),
                'facebook_url' => get_setting($pdo, 'facebook_url', 'https://www.facebook.com/profile.php?id=61576107364762'),
                'instagram_url' => get_setting($pdo, 'instagram_url', 'https://www.instagram.com/creamod3d.fr/'),
                'maintenance_mode' => (bool)get_setting($pdo, 'maintenance_mode', '0'),
                'items_per_page_shop' => (int)get_setting($pdo, 'items_per_page_shop', '12')
            ];
            $feedback_message = "Paramètres sauvegardés avec succès.";
            $feedback_type = 'success';
        } else {
            if (empty($feedback_message)) { // Si aucun message d'erreur spécifique n'a été défini (ex: erreur BDD)
                 $feedback_message = "Erreur lors de la sauvegarde d'un ou plusieurs paramètres.";
                 $feedback_type = 'error';
            }
        }
    } else {
        $feedback_message = "Veuillez corriger les erreurs suivantes :<br><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        $feedback_type = 'error';
        // Pour que le formulaire réaffiche les valeurs soumises en cas d'erreur :
        foreach ($new_values_from_post as $key => $value) {
            if ($key === 'maintenance_mode') {
                $current_settings[$key] = ($value === '1'); 
            } else {
                $current_settings[$key] = htmlspecialchars($value); // Nettoyer avant de réafficher
            }
        }
    }
}

// Compteur pour la sidebar (si vous l'utilisez dans admin_sidebar.php)
$nombre_devis_en_attente = 0;
if ($pdo) {
    try {
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM demandes_devis WHERE statut = 'En attente'");
        $nombre_devis_en_attente = $stmt_count->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur admin_settings (calcul devis en attente): " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres du Site - CréaMod3D Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .form-input { @apply w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm; }
        .form-label { @apply block text-sm font-medium text-gray-700 mb-1; }
        .form-checkbox { @apply h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500; }
        .feedback-banner { padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .feedback-success { background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .feedback-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php 
            $page_title_admin = "Paramètres du Site"; 
            include 'includes/admin_header_layout.php'; 
        ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">

                <?php if ($feedback_message): ?>
                <div class="feedback-banner <?php echo $feedback_type === 'success' ? 'feedback-success' : 'feedback-error'; ?>" role="alert">
                    <?php echo $feedback_message; ?>
                </div>
                <?php endif; ?>

                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Configuration Générale</h2>
                    
                    <form method="POST" action="admin_settings.php" class="space-y-6">
                        <div>
                            <label for="site_contact_email" class="form-label">Email de contact principal du site</label>
                            <input type="email" name="site_contact_email" id="site_contact_email" class="form-input" value="<?php echo htmlspecialchars($current_settings['site_contact_email']); ?>" required>
                        </div>

                        <div>
                            <label for="facebook_url" class="form-label">URL de la page Facebook</label>
                            <input type="url" name="facebook_url" id="facebook_url" class="form-input" value="<?php echo htmlspecialchars($current_settings['facebook_url']); ?>" placeholder="https://www.facebook.com/votrepseudo">
                        </div>

                        <div>
                            <label for="instagram_url" class="form-label">URL du profil Instagram</label>
                            <input type="url" name="instagram_url" id="instagram_url" class="form-input" value="<?php echo htmlspecialchars($current_settings['instagram_url']); ?>" placeholder="https://www.instagram.com/votrepseudo">
                        </div>
                        
                        <hr>

                        <h3 class="text-lg font-medium text-gray-900 pt-2">Paramètres E-commerce (Exemples)</h3>
                        <div>
                            <label for="items_per_page_shop" class="form-label">Produits par page (boutique)</label>
                            <input type="number" name="items_per_page_shop" id="items_per_page_shop" min="1" max="100" class="form-input w-auto" value="<?php echo htmlspecialchars($current_settings['items_per_page_shop']); ?>">
                        </div>

                        <hr>
                        
                        <h3 class="text-lg font-medium text-gray-900 pt-2">Maintenance</h3>
                        <div class="flex items-center">
                            <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" class="form-checkbox" <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                            <label for="maintenance_mode" class="ml-2 block text-sm text-gray-900">Activer le mode maintenance</label>
                        </div>
                        <p class="text-xs text-gray-500">Si activé, un message de maintenance sera affiché aux visiteurs (logique à implémenter sur le site public).</p>
                        
                        <hr>

                        <h3 class="text-lg font-medium text-gray-900 pt-2">Changement de Mot de Passe Administrateur</h3>
                        <p class="text-sm text-gray-600 mb-2">Pour changer votre mot de passe, veuillez utiliser une procédure sécurisée (non implémentée dans cet exemple simple).</p>
                        <div class="space-y-2">
                             <div>
                                <label for="current_admin_password" class="form-label">Mot de passe actuel (Admin)</label>
                                <input type="password" name="current_admin_password" id="current_admin_password" class="form-input" disabled placeholder="Fonctionnalité à développer">
                            </div>
                             <div>
                                <label for="new_admin_password" class="form-label">Nouveau mot de passe (Admin)</label>
                                <input type="password" name="new_admin_password" id="new_admin_password" class="form-input" disabled>
                            </div>
                             <div>
                                <label for="confirm_new_admin_password" class="form-label">Confirmer nouveau mot de passe (Admin)</label>
                                <input type="password" name="confirm_new_admin_password" id="confirm_new_admin_password" class="form-input" disabled>
                            </div>
                        </div>

                        <div class="pt-5">
                            <button type="submit" name="save_settings" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Sauvegarder les Paramètres
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
        const sidebarElAdminSettings = document.querySelector('aside.admin-sidebar');
        const mobileMenuButtonAdminSettings = document.getElementById('mobile-menu-button'); 
        
        if (mobileMenuButtonAdminSettings && sidebarElAdminSettings) {
            mobileMenuButtonAdminSettings.addEventListener('click', () => {
                sidebarElAdminSettings.classList.toggle('-translate-x-full');
            });
        }
    </script>
</body>
</html>
