<?php
// admin_dashboard.php
session_start(); // Démarrer la session PHP

// Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Si non connecté, rediriger vers la page de connexion
    header('Location: admin_login.php');
    exit;
}

// Inclure la configuration de la base de données si nécessaire pour des opérations futures
require_once 'includes/db_config.php';
$pdo = connect_db(); // Établir la connexion

// Récupérer le nom d'utilisateur de la session
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Logique pour récupérer des informations (par exemple, nombre de devis en attente)
$nombre_devis_en_attente = 0;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM demandes_devis WHERE statut = 'En attente'");
        $nombre_devis_en_attente = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Gérer l'erreur, par exemple, logger et mettre une valeur par défaut
        error_log("Erreur lors de la récupération du nombre de devis: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - CréaMod3D</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* bg-gray-100 */
        }
        .admin-sidebar {
            background-color: #1f2937; /* bg-gray-800 */
            color: #e5e7eb; /* text-gray-200 */
        }
        .admin-sidebar a {
            color: #d1d5db; /* text-gray-400 */
            transition: background-color 0.2s, color 0.2s;
        }
        .admin-sidebar a:hover, .admin-sidebar a.active {
            background-color: #374151; /* bg-gray-700 */
            color: white;
        }
        .admin-content {
            background-color: #ffffff; /* bg-white */
        }
        .stat-card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
            padding: 1.5rem;
            text-align: center;
        }
        .stat-card .stat-number {
            font-size: 2.25rem; /* text-4xl */
            font-weight: bold;
            color: #A8C63F; /* Couleur accent */
        }
        .stat-card .stat-label {
            font-size: 0.875rem; /* text-sm */
            color: #6b7280; /* text-gray-500 */
            margin-top: 0.5rem;
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="admin-sidebar w-64 space-y-6 py-7 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out">
        <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4">
            <img src="assets/images/logo.png" alt="Logo CréaMod3D" class="h-10 w-auto" onerror="this.style.display='none';">
            <span class="text-2xl font-bold text-white">CréaMod3D</span>
        </a>

        <nav class="space-y-1">
            <a href="admin_dashboard.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium active">
                <i class="fas fa-tachometer-alt fa-fw mr-2"></i>
                Tableau de bord
            </a>
            <a href="admin_manage_quotes.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-file-invoice-dollar fa-fw mr-2"></i>
                Gestion des Devis
                <?php if ($nombre_devis_en_attente > 0): ?>
                    <span class="ml-auto bg-yellow-500 text-gray-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo $nombre_devis_en_attente; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_manage_products.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-cubes fa-fw mr-2"></i>
                Gestion des Produits
            </a>
            <a href="admin_manage_contacts.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-envelope-open-text fa-fw mr-2"></i>
                Messages de Contact
            </a>
            <a href="admin_settings.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-cogs fa-fw mr-2"></i>
                Paramètres
            </a>
        </nav>
        
        <div class="absolute bottom-0 left-0 right-0 p-4">
             <a href="index.php" target="_blank" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium text-gray-400 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-globe fa-fw mr-2"></i>
                Voir le site public
            </a>
            <a href="admin_logout.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium text-gray-400 hover:bg-red-600 hover:text-white mt-2">
                <i class="fas fa-sign-out-alt fa-fw mr-2"></i>
                Déconnexion
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <button class="md:hidden text-gray-500 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                        <span class="sr-only">Ouvrir la sidebar</span>
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-gray-800">Tableau de Bord</h1>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-600 mr-3">Bonjour, <?php echo htmlspecialchars($admin_username); ?> !</span>
                        </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">Aperçu général</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $nombre_devis_en_attente; ?></div>
                        <div class="stat-label">Devis en attente</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">12</div> 
                        <div class="stat-label">Commandes ce mois-ci</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">5</div>
                        <div class="stat-label">Nouveaux messages</div>
                    </div>
                </div>

                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4">Actions rapides</h3>
                    <div class="space-x-0 space-y-3 md:space-x-3 md:space-y-0 flex flex-col md:flex-row">
                        <a href="admin_manage_quotes.php?filter=pending" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md inline-flex items-center transition duration-150">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Voir les devis en attente
                        </a>
                        <a href="admin_manage_products.php?action=add" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md inline-flex items-center transition duration-150">
                            <i class="fas fa-plus-circle mr-2"></i> Ajouter un nouveau produit
                        </a>
                    </div>
                </div>

                <div class="mt-8">
                    <p class="text-gray-600">D'autres modules et statistiques apparaîtront ici à mesure que l'interface d'administration sera développée.</p>
                </div>

            </div>
        </main>
    </div>
    <script>
        // Script simple pour la sidebar mobile (à améliorer si besoin)
        const sidebar = document.querySelector('aside');
        const mobileMenuButton = document.querySelector('header button.md\\:hidden');
        if (mobileMenuButton && sidebar) {
            mobileMenuButton.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
    </script>
</body>
</html>
