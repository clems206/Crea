<?php
// admin_manage_quotes.php
session_start(); // Démarrer la session PHP

// Vérifier si l'administrateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once 'includes/db_config.php'; // Inclure la configuration de la BDD
$pdo = connect_db(); // Établir la connexion

$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$feedback_message = '';
$feedback_type = ''; // 'success' ou 'error'

// --- Traitement des actions (changement de statut, suppression) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $quote_id = filter_input(INPUT_POST, 'quote_id', FILTER_VALIDATE_INT);

        if ($quote_id) {
            if ($_POST['action'] === 'update_status' && isset($_POST['new_status'])) {
                $new_status = htmlspecialchars(trim($_POST['new_status']));
                $allowed_statuses = ['En attente', 'En cours de traitement', 'Traité', 'Annulé', 'Facturé']; // Ajoutez les statuts que vous souhaitez gérer
                if (in_array($new_status, $allowed_statuses)) {
                    try {
                        $sql = "UPDATE demandes_devis SET statut = :statut WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':statut', $new_status);
                        $stmt->bindParam(':id', $quote_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $feedback_message = "Le statut du devis ID #$quote_id a été mis à jour avec succès à \"$new_status\".";
                        $feedback_type = 'success';
                    } catch (PDOException $e) {
                        error_log("Erreur lors de la mise à jour du statut du devis: " . $e->getMessage());
                        $feedback_message = "Erreur lors de la mise à jour du statut du devis.";
                        $feedback_type = 'error';
                    }
                } else {
                    $feedback_message = "Statut non valide.";
                    $feedback_type = 'error';
                }
            } elseif ($_POST['action'] === 'delete_quote') {
                // Optionnel : supprimer aussi le fichier uploadé s'il existe
                try {
                    // D'abord, récupérer le chemin du fichier pour le supprimer du serveur
                    $stmt_file = $pdo->prepare("SELECT fichier_upload_path FROM demandes_devis WHERE id = :id");
                    $stmt_file->bindParam(':id', $quote_id, PDO::PARAM_INT);
                    $stmt_file->execute();
                    $file_to_delete = $stmt_file->fetchColumn();

                    if ($file_to_delete && file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }

                    // Ensuite, supprimer l'entrée de la base de données
                    $sql = "DELETE FROM demandes_devis WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $quote_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $feedback_message = "Le devis ID #$quote_id a été supprimé avec succès.";
                    $feedback_type = 'success';
                } catch (PDOException $e) {
                    error_log("Erreur lors de la suppression du devis: " . $e->getMessage());
                    $feedback_message = "Erreur lors de la suppression du devis.";
                    $feedback_type = 'error';
                }
            }
        } else {
            $feedback_message = "ID de devis invalide pour l'action.";
            $feedback_type = 'error';
        }
    }
}


// --- Récupération des devis ---
$filter_status = $_GET['filter_status'] ?? 'Tous'; // Statut par défaut ou celui filtré
$allowed_filter_statuses = ['Tous', 'En attente', 'En cours de traitement', 'Traité', 'Annulé', 'Facturé'];

if (!in_array($filter_status, $allowed_filter_statuses)) {
    $filter_status = 'Tous'; // Sécurité : si le filtre n'est pas valide, afficher tous
}

$sql_quotes = "SELECT * FROM demandes_devis";
if ($filter_status !== 'Tous') {
    $sql_quotes .= " WHERE statut = :statut";
}
$sql_quotes .= " ORDER BY date_soumission DESC";

$stmt_quotes = $pdo->prepare($sql_quotes);
if ($filter_status !== 'Tous') {
    $stmt_quotes->bindParam(':statut', $filter_status);
}
$stmt_quotes->execute();
$quotes = $stmt_quotes->fetchAll(PDO::FETCH_ASSOC);

// Compteur pour la sidebar (récupéré de admin_dashboard.php pour cohérence)
$nombre_devis_en_attente = 0;
if ($pdo) {
    try {
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM demandes_devis WHERE statut = 'En attente'");
        $nombre_devis_en_attente = $stmt_count->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du nombre de devis: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Devis - CréaMod3D Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .admin-sidebar { background-color: #1f2937; color: #e5e7eb; }
        .admin-sidebar a { color: #d1d5db; transition: background-color 0.2s, color 0.2s; }
        .admin-sidebar a:hover, .admin-sidebar a.active { background-color: #374151; color: white; }
        .table-custom { min-width: 100%; border-collapse: collapse; }
        .table-custom th, .table-custom td { padding: 0.75rem 1rem; border: 1px solid #e5e7eb; text-align: left; font-size: 0.875rem; }
        .table-custom th { background-color: #f9fafb; font-weight: 600; color: #374151; }
        .table-custom tbody tr:nth-child(even) { background-color: #f9fafb; }
        .table-custom tbody tr:hover { background-color: #f3f4f6; }
        .action-btn { padding: 0.3rem 0.6rem; border-radius: 0.25rem; font-size: 0.8rem; margin-right: 0.25rem; transition: background-color 0.2s; }
        .btn-view { background-color: #3b82f6; color: white; } .btn-view:hover { background-color: #2563eb; }
        .btn-edit { background-color: #f59e0b; color: white; } .btn-edit:hover { background-color: #d97706; }
        .btn-delete { background-color: #ef4444; color: white; } .btn-delete:hover { background-color: #dc2626; }
        .status-select { padding: 0.3rem 0.5rem; border-radius: 0.25rem; border: 1px solid #d1d5db; font-size: 0.8rem; }
        .feedback-banner { padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .feedback-success { background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .feedback-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="admin-sidebar w-64 space-y-6 py-7 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out">
        <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4">
            <img src="assets/images/logo.png" alt="Logo CréaMod3D" class="h-10 w-auto" onerror="this.style.display='none';">
            <span class="text-2xl font-bold text-white">CréaMod3D</span>
        </a>
        <nav class="space-y-1">
            <a href="admin_dashboard.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-tachometer-alt fa-fw mr-2"></i> Tableau de bord
            </a>
            <a href="admin_manage_quotes.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium active">
                <i class="fas fa-file-invoice-dollar fa-fw mr-2"></i> Gestion des Devis
                <?php if ($nombre_devis_en_attente > 0): ?>
                    <span class="ml-auto bg-yellow-500 text-gray-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo $nombre_devis_en_attente; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_manage_products.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-cubes fa-fw mr-2"></i> Gestion des Produits
            </a>
            <a href="admin_manage_contacts.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-envelope-open-text fa-fw mr-2"></i> Messages de Contact
            </a>
            <a href="admin_settings.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium">
                <i class="fas fa-cogs fa-fw mr-2"></i> Paramètres
            </a>
        </nav>
        <div class="absolute bottom-0 left-0 right-0 p-4">
            <a href="index.php" target="_blank" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium text-gray-400 hover:bg-gray-700 hover:text-white">
                <i class="fas fa-globe fa-fw mr-2"></i> Voir le site public
            </a>
            <a href="admin_logout.php" class="flex items-center space-x-3 rounded-md px-3 py-2 text-sm font-medium text-gray-400 hover:bg-red-600 hover:text-white mt-2">
                <i class="fas fa-sign-out-alt fa-fw mr-2"></i> Déconnexion
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <button id="mobile-menu-button" class="md:hidden text-gray-500 hover:text-gray-600 focus:outline-none">
                        <span class="sr-only">Ouvrir la sidebar</span> <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-gray-800">Gestion des Devis</h1>
                    <span class="text-sm text-gray-600">Bonjour, <?php echo htmlspecialchars($admin_username); ?> !</span>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">
                
                <?php if ($feedback_message): ?>
                <div class="feedback-banner <?php echo $feedback_type === 'success' ? 'feedback-success' : 'feedback-error'; ?>" role="alert">
                    <?php echo htmlspecialchars($feedback_message); ?>
                </div>
                <?php endif; ?>

                <div class="mb-6 flex justify-between items-center">
                    <h2 class="text-2xl font-semibold text-gray-700">Liste des Demandes de Devis</h2>
                    <form method="GET" action="admin_manage_quotes.php" class="flex items-center space-x-2">
                        <label for="filter_status" class="text-sm font-medium text-gray-700">Filtrer par statut :</label>
                        <select name="filter_status" id="filter_status" class="status-select" onchange="this.form.submit()">
                            <?php foreach ($allowed_filter_statuses as $status_option): ?>
                                <option value="<?php echo htmlspecialchars($status_option); ?>" <?php echo $filter_status === $status_option ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="text-sm bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md">Filtrer</button></noscript>
                    </form>
                </div>

                <div class="bg-white shadow rounded-lg overflow-x-auto">
                    <?php if (empty($quotes)): ?>
                        <p class="p-6 text-gray-500">Aucune demande de devis trouvée pour le statut "<?php echo htmlspecialchars($filter_status); ?>".</p>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Email</th>
                                    <th>Type Projet</th>
                                    <th>Date</th>
                                    <th>Montant Est.</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotes as $quote): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($quote['id']); ?></td>
                                    <td><?php echo htmlspecialchars($quote['prenom'] . ' ' . $quote['nom']); ?></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($quote['email']); ?>" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($quote['email']); ?></a></td>
                                    <td><?php echo htmlspecialchars($quote['type_projet']); ?></td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($quote['date_soumission'])); ?></td>
                                    <td><?php echo number_format($quote['montant_estime'], 2, ',', ' '); ?> €</td>
                                    <td>
                                        <form method="POST" action="admin_manage_quotes.php?filter_status=<?php echo urlencode($filter_status); ?>" class="inline-block">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                                            <select name="new_status" class="status-select" onchange="this.form.submit()">
                                                <?php foreach ($allowed_statuses as $status_option_item): ?>
                                                <option value="<?php echo htmlspecialchars($status_option_item); ?>" <?php echo $quote['statut'] === $status_option_item ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status_option_item); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <noscript><button type="submit" class="text-xs bg-gray-200 px-1 rounded">OK</button></noscript>
                                        </form>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <button onclick="viewQuoteDetails(<?php echo htmlspecialchars(json_encode($quote, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)); ?>)" class="action-btn btn-view" title="Voir Détails"><i class="fas fa-eye"></i></button>
                                        <form method="POST" action="admin_manage_quotes.php?filter_status=<?php echo urlencode($filter_status); ?>" class="inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce devis ID #<?php echo $quote['id']; ?> ? Cette action est irréversible.');">
                                            <input type="hidden" name="action" value="delete_quote">
                                            <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                                            <button type="submit" class="action-btn btn-delete" title="Supprimer"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="quoteDetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden" x-data="{ open: false }" @view-quote.window="open = true; loadQuoteDetails($event.detail)">
        <div class="relative mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white" @click.away="open = false">
            <div class="mt-3 text-center">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalTitle">Détails du Devis #<span id="modalQuoteId"></span></h3>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Fermer</span>
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="mt-4 text-left space-y-3" id="modalBody">
                    </div>
                <div class="items-center px-4 py-3 mt-4 border-t">
                    <button @click="open = false" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300 sm:w-auto sm:text-sm">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script pour la sidebar mobile
        const sidebar = document.querySelector('aside.admin-sidebar');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        if (mobileMenuButton && sidebar) {
            mobileMenuButton.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        // Alpine.js est utilisé pour le modal, mais la fonction de chargement est en JS pur.
        function viewQuoteDetails(quoteData) {
            // Dispatch un événement pour qu'Alpine.js ouvre le modal et charge les données
            const event = new CustomEvent('view-quote', { detail: quoteData });
            window.dispatchEvent(event);
        }
        
        function loadQuoteDetails(quote) {
            document.getElementById('modalQuoteId').textContent = quote.id;
            const modalBody = document.getElementById('modalBody');
            let detailsHtml = `
                <p><strong>Client:</strong> ${escapeHtml(quote.prenom)} ${escapeHtml(quote.nom)}</p>
                <p><strong>Email:</strong> <a href="mailto:${escapeHtml(quote.email)}" class="text-blue-600 hover:underline">${escapeHtml(quote.email)}</a></p>
                ${quote.telephone ? `<p><strong>Téléphone:</strong> ${escapeHtml(quote.telephone)}</p>` : ''}
                <p><strong>Type de projet:</strong> ${escapeHtml(quote.type_projet)}</p>
                <p><strong>Date de soumission:</strong> ${new Date(quote.date_soumission).toLocaleString('fr-FR')}</p>
                <p><strong>Statut actuel:</strong> ${escapeHtml(quote.statut)}</p>
                <p><strong>Montant estimé:</strong> ${parseFloat(quote.montant_estime).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}</p>
                <hr class="my-2">
                <p><strong>Description du projet:</strong></p>
                <div class="p-2 bg-gray-50 rounded border max-h-40 overflow-y-auto">${nl2br(escapeHtml(quote.description_projet))}</div>
            `;

            if (quote.type_projet === 'prenom_lumineux' || quote.type_projet === 'logo_lumineux') {
                detailsHtml += `<hr class="my-2">
                <p><strong>Détails spécifiques :</strong></p>
                <ul>
                    ${quote.texte_personnalise ? `<li><strong>Texte personnalisé:</strong> ${escapeHtml(quote.texte_personnalise)}</li>` : ''}
                    ${quote.couleur_contour ? `<li><strong>Couleur contour:</strong> ${escapeHtml(quote.couleur_contour)}</li>` : ''}
                    ${quote.eclairage ? `<li><strong>Éclairage:</strong> ${escapeHtml(quote.eclairage)}</li>` : ''}
                    ${quote.option_rgb && quote.eclairage === 'rgb' ? `<li><strong>Option RGB:</strong> ${escapeHtml(quote.option_rgb)}</li>` : ''}
                </ul>`;
            }

            if (quote.fichier_nom_original) {
                detailsHtml += `<hr class="my-2">
                <p><strong>Fichier joint:</strong> ${escapeHtml(quote.fichier_nom_original)}</p>
                <p><small>(Chemin serveur: ${escapeHtml(quote.fichier_upload_path)})</small></p>
                <p><small>Pour télécharger, une fonctionnalité admin dédiée serait nécessaire pour des raisons de sécurité.</small></p>`;
            }
            modalBody.innerHTML = detailsHtml;
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || typeof unsafe === 'undefined') return '';
            return unsafe
                 .toString()
                 .replace(/&/g, "&amp;")
                 .replace(/</g, "&lt;")
                 .replace(/>/g, "&gt;")
                 .replace(/"/g, "&quot;")
                 .replace(/'/g, "&#039;");
        }
        function nl2br(str) {
            if (typeof str === 'undefined' || str === null) {
                return '';
            }
            return str.replace(/(\r\n|\n\r|\r|\n)/g, '<br>$1');
        }

    </script>
    </body>
</html>
