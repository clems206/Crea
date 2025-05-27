<?php
// admin_manage_products.php
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

// --- Définition du chemin pour les uploads d'images de produits ---
define('PRODUCT_IMAGE_UPLOAD_DIR', 'uploads/products/');
if (!is_dir(PRODUCT_IMAGE_UPLOAD_DIR)) {
    mkdir(PRODUCT_IMAGE_UPLOAD_DIR, 0755, true);
}

// --- Variables pour le formulaire d'ajout/modification ---
$edit_mode = false;
$product_id_to_edit = null;
$product_data = [
    'nom' => '',
    'description' => '',
    'prix_base' => '',
    'categorie' => '',
    'stock' => 0,
    'est_actif' => 1, // Par défaut actif
    'image_url' => ''
];

// --- Traitement du formulaire d'ajout/modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $description = trim($_POST['description'] ?? ''); // Peut contenir du HTML simple, à nettoyer/valider selon besoin
    $prix_base = filter_input(INPUT_POST, 'prix_base', FILTER_VALIDATE_FLOAT);
    $categorie = htmlspecialchars(trim($_POST['categorie'] ?? ''));
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $est_actif = isset($_POST['est_actif']) ? 1 : 0;
    $current_image_url = htmlspecialchars(trim($_POST['current_image_url'] ?? ''));

    // Validation simple
    if (empty($nom) || $prix_base === false || $prix_base < 0) {
        $feedback_message = "Le nom du produit et un prix valide sont requis.";
        $feedback_type = 'error';
    } else {
        $new_image_url = $current_image_url; // Garder l'ancienne image par défaut

        // Gestion de l'upload d'image
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
            $image_name = basename($_FILES['product_image']['name']);
            $image_tmp_name = $_FILES['product_image']['tmp_name'];
            $image_size = $_FILES['product_image']['size'];
            $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 2 * 1024 * 1024; // 2MB

            if (in_array($image_ext, $allowed_extensions) && $image_size <= $max_file_size) {
                $safe_image_name = preg_replace("/[^a-zA-Z0-9\._-]/", "", $image_name);
                $new_image_filename = uniqid('prod_', true) . '_' . $safe_image_name;
                $destination = PRODUCT_IMAGE_UPLOAD_DIR . $new_image_filename;

                if (move_uploaded_file($image_tmp_name, $destination)) {
                    // Supprimer l'ancienne image si une nouvelle est uploadée et que l'ancienne existe
                    if ($product_id && !empty($current_image_url) && file_exists($current_image_url) && $current_image_url !== $destination) {
                        unlink($current_image_url);
                    }
                    $new_image_url = $destination;
                } else {
                    $feedback_message = "Erreur lors du téléversement de la nouvelle image.";
                    $feedback_type = 'error';
                }
            } else {
                $feedback_message = "Fichier image non valide (extensions autorisées: jpg, jpeg, png, gif, webp; taille max: 2MB).";
                $feedback_type = 'error';
            }
        }

        if ($feedback_type !== 'error') { // Continuer seulement si pas d'erreur d'image
            try {
                if ($product_id) { // Modification
                    $sql = "UPDATE produits SET nom = :nom, description = :description, prix_base = :prix_base, categorie = :categorie, stock = :stock, est_actif = :est_actif, image_url = :image_url WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
                } else { // Ajout
                    $sql = "INSERT INTO produits (nom, description, prix_base, categorie, stock, est_actif, image_url) VALUES (:nom, :description, :prix_base, :categorie, :stock, :est_actif, :image_url)";
                    $stmt = $pdo->prepare($sql);
                }
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':prix_base', $prix_base);
                $stmt->bindParam(':categorie', $categorie);
                $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
                $stmt->bindParam(':est_actif', $est_actif, PDO::PARAM_INT);
                $stmt->bindParam(':image_url', $new_image_url);
                $stmt->execute();

                $feedback_message = $product_id ? "Produit mis à jour avec succès." : "Produit ajouté avec succès.";
                $feedback_type = 'success';
                 // Réinitialiser pour le prochain ajout ou vider le formulaire d'édition
                $product_id_to_edit = null; 
                $edit_mode = false;
                $product_data = array_fill_keys(array_keys($product_data), ''); // Vider le formulaire
                $product_data['est_actif'] = 1; $product_data['stock'] = 0;


            } catch (PDOException $e) {
                error_log("Erreur lors de la sauvegarde du produit: " . $e->getMessage());
                $feedback_message = "Erreur lors de la sauvegarde du produit.";
                $feedback_type = 'error';
            }
        }
    }
     if ($feedback_type === 'error' && $product_id) { // Si erreur en mode édition, recharger les données pour le formulaire
        $product_id_to_edit = $product_id;
        // Les données postées sont déjà dans $product_data via la préparation du formulaire plus bas
    }
}

// --- Traitement de la suppression ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id_to_delete = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id_to_delete) {
        try {
            // Récupérer l'URL de l'image pour la supprimer
            $stmt_img = $pdo->prepare("SELECT image_url FROM produits WHERE id = :id");
            $stmt_img->bindParam(':id', $product_id_to_delete, PDO::PARAM_INT);
            $stmt_img->execute();
            $image_to_delete = $stmt_img->fetchColumn();

            if ($image_to_delete && file_exists($image_to_delete)) {
                unlink($image_to_delete);
            }

            $sql = "DELETE FROM produits WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $product_id_to_delete, PDO::PARAM_INT);
            $stmt->execute();
            $feedback_message = "Produit supprimé avec succès.";
            $feedback_type = 'success';
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du produit: " . $e->getMessage());
            $feedback_message = "Erreur lors de la suppression du produit.";
            $feedback_type = 'error';
        }
    }
}

// --- Traitement du changement de statut actif/inactif ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active_status'])) {
    $product_id_toggle = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);

    if ($product_id_toggle !== false && $current_status !== false) {
        $new_status = ($current_status == 1) ? 0 : 1;
        try {
            $sql = "UPDATE produits SET est_actif = :est_actif WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':est_actif', $new_status, PDO::PARAM_INT);
            $stmt->bindParam(':id', $product_id_toggle, PDO::PARAM_INT);
            $stmt->execute();
            $feedback_message = "Statut du produit mis à jour.";
            $feedback_type = 'success';
        } catch (PDOException $e) {
            error_log("Erreur lors du changement de statut du produit: " . $e->getMessage());
            $feedback_message = "Erreur lors du changement de statut.";
            $feedback_type = 'error';
        }
    }
}


// --- Préparation pour le mode édition ---
if (isset($_GET['edit_id'])) {
    $product_id_to_edit = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($product_id_to_edit) {
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = :id");
        $stmt->bindParam(':id', $product_id_to_edit, PDO::PARAM_INT);
        $stmt->execute();
        $data_for_edit = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data_for_edit) {
            $product_data = $data_for_edit;
            $edit_mode = true;
        } else {
            $feedback_message = "Produit non trouvé pour modification.";
            $feedback_type = 'error';
            $product_id_to_edit = null; // Réinitialiser si non trouvé
        }
    }
}
// Si une erreur s'est produite lors de la soumission du formulaire en mode édition,
// les données postées sont prioritaires pour réafficher le formulaire avec les erreurs.
if ($feedback_type === 'error' && isset($_POST['save_product']) && isset($_POST['product_id'])) {
    $product_data['nom'] = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $product_data['description'] = trim($_POST['description'] ?? '');
    $product_data['prix_base'] = filter_input(INPUT_POST, 'prix_base', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $product_data['categorie'] = htmlspecialchars(trim($_POST['categorie'] ?? ''));
    $product_data['stock'] = filter_input(INPUT_POST, 'stock', FILTER_SANITIZE_NUMBER_INT);
    $product_data['est_actif'] = isset($_POST['est_actif']) ? 1 : 0;
    $product_data['image_url'] = htmlspecialchars(trim($_POST['current_image_url'] ?? '')); // Garder l'image actuelle en cas d'erreur
    $edit_mode = true; // Rester en mode édition
    $product_id_to_edit = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
}



// --- Récupération de la liste des produits ---
$stmt_products = $pdo->query("SELECT * FROM produits ORDER BY date_creation DESC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Compteur pour la sidebar
$nombre_devis_en_attente = $pdo->query("SELECT COUNT(*) FROM demandes_devis WHERE statut = 'En attente'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - CréaMod3D Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .admin-sidebar { background-color: #1f2937; color: #e5e7eb; }
        .admin-sidebar a { color: #d1d5db; transition: background-color 0.2s, color 0.2s; }
        .admin-sidebar a:hover, .admin-sidebar a.active { background-color: #374151; color: white; }
        .table-custom th, .table-custom td { padding: 0.5rem 0.75rem; border: 1px solid #e5e7eb; text-align: left; font-size: 0.875rem; vertical-align: middle;}
        .table-custom th { background-color: #f9fafb; font-weight: 600; color: #374151; }
        .table-custom img { max-height: 50px; max-width: 50px; border-radius: 0.25rem; }
        .action-btn { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; margin: 0.1rem; }
        .feedback-banner { padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .feedback-success { background-color: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .feedback-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .form-input { @apply w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm; }
        .form-label { @apply block text-sm font-medium text-gray-700 mb-1; }
        /* Simple WYSIWYG placeholder */
        .wysiwyg-editor { min-height: 150px; border: 1px solid #d1d5db; padding: 0.5rem; border-radius: 0.25rem; }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <?php include 'includes/admin_sidebar.php'; // Assurez-vous que ce fichier existe et contient la sidebar ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include 'includes/admin_header_layout.php'; // Assurez-vous que ce fichier existe ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">

                <?php if ($feedback_message): ?>
                <div class="feedback-banner <?php echo $feedback_type === 'success' ? 'feedback-success' : 'feedback-error'; ?>" role="alert">
                    <?php echo $feedback_message; // Pas besoin de htmlspecialchars ici car on contrôle le message ?>
                </div>
                <?php endif; ?>

                <div id="productFormContainer" class="mb-8 bg-white shadow rounded-lg p-6 <?php echo ($edit_mode || (isset($_POST['save_product']) && $feedback_type === 'error')) ? '' : 'hidden'; ?>">
                    <h2 class="text-2xl font-semibold text-gray-700 mb-4"><?php echo $edit_mode ? 'Modifier le Produit' : 'Ajouter un Nouveau Produit'; ?></h2>
                    <form method="POST" action="admin_manage_products.php" enctype="multipart/form-data" class="space-y-4">
                        <?php if ($edit_mode && $product_id_to_edit): ?>
                            <input type="hidden" name="product_id" value="<?php echo $product_id_to_edit; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($product_data['image_url']); ?>">

                        <div>
                            <label for="nom" class="form-label">Nom du Produit *</label>
                            <input type="text" name="nom" id="nom" class="form-input" value="<?php echo htmlspecialchars($product_data['nom']); ?>" required>
                        </div>
                        <div>
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" rows="5" class="form-input wysiwyg-editor"><?php echo htmlspecialchars($product_data['description']); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Vous pouvez utiliser des balises HTML simples pour la mise en forme.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="prix_base" class="form-label">Prix de Base (€) *</label>
                                <input type="number" name="prix_base" id="prix_base" step="0.01" min="0" class="form-input" value="<?php echo htmlspecialchars($product_data['prix_base']); ?>" required>
                            </div>
                            <div>
                                <label for="categorie" class="form-label">Catégorie</label>
                                <input type="text" name="categorie" id="categorie" class="form-input" value="<?php echo htmlspecialchars($product_data['categorie']); ?>" placeholder="Ex: Prénom lumineux, Figurine">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="stock" class="form-label">Stock</label>
                                <input type="number" name="stock" id="stock" min="0" class="form-input" value="<?php echo htmlspecialchars($product_data['stock']); ?>">
                            </div>
                            <div class="flex items-center pt-6">
                                <input type="checkbox" name="est_actif" id="est_actif" value="1" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" <?php echo ($product_data['est_actif'] == 1) ? 'checked' : ''; ?>>
                                <label for="est_actif" class="ml-2 block text-sm text-gray-900">Produit Actif (visible sur le site)</label>
                            </div>
                        </div>
                        <div>
                            <label for="product_image" class="form-label">Image du Produit</label>
                            <input type="file" name="product_image" id="product_image" class="form-input">
                            <?php if ($edit_mode && !empty($product_data['image_url']) && file_exists($product_data['image_url'])): ?>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Image actuelle :</p>
                                    <img src="<?php echo htmlspecialchars($product_data['image_url']); ?>?t=<?php echo time(); ?>" alt="Image actuelle" class="h-20 w-auto rounded border p-1">
                                    <p class="text-xs text-gray-500">Laissez vide pour conserver l'image actuelle. Uploader une nouvelle image la remplacera.</p>
                                </div>
                            <?php elseif ($edit_mode && !empty($product_data['image_url'])): ?>
                                 <p class="text-xs text-red-500 mt-1">Image actuelle non trouvée: <?php echo htmlspecialchars($product_data['image_url']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="toggleProductFormVisibility(false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">Annuler</button>
                            <button type="submit" name="save_product" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-md">
                                <?php echo $edit_mode ? 'Mettre à jour le Produit' : 'Ajouter le Produit'; ?>
                            </button>
                        </div>
                    </form>
                </div>


                <div class="mb-6 flex justify-between items-center">
                    <h2 class="text-2xl font-semibold text-gray-700">Liste des Produits</h2>
                    <button onclick="toggleProductFormVisibility(true, true)" class="bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md inline-flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> Ajouter un Produit
                    </button>
                </div>

                <div class="bg-white shadow rounded-lg overflow-x-auto">
                    <?php if (empty($products)): ?>
                        <p class="p-6 text-gray-500">Aucun produit trouvé. Commencez par en ajouter un !</p>
                    <?php else: ?>
                        <table class="table-custom w-full">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Nom</th>
                                    <th>Catégorie</th>
                                    <th>Prix</th>
                                    <th>Stock</th>
                                    <th>Actif</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>#<?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['image_url']) && file_exists($product['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>?t=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($product['nom']); ?>">
                                        <?php else: ?>
                                            <span class="text-xs text-gray-400">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-medium text-gray-900"><?php echo htmlspecialchars($product['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($product['categorie'] ?: '-'); ?></td>
                                    <td><?php echo number_format($product['prix_base'], 2, ',', ' '); ?> €</td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <form method="POST" action="admin_manage_products.php" class="inline-block">
                                            <input type="hidden" name="action" value="toggle_active_status">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $product['est_actif']; ?>">
                                            <button type="submit" name="toggle_active_status" class="action-btn <?php echo $product['est_actif'] ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'; ?> text-white" title="<?php echo $product['est_actif'] ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas <?php echo $product['est_actif'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <a href="admin_manage_products.php?edit_id=<?php echo $product['id']; ?>#productFormContainer" class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white" title="Modifier"><i class="fas fa-edit"></i></a>
                                        <form method="POST" action="admin_manage_products.php" class="inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer le produit \'<?php echo htmlspecialchars(addslashes($product['nom'])); ?>\' ? Cette action est irréversible.');">
                                            <input type="hidden" name="delete_product" value="1">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="action-btn bg-red-500 hover:bg-red-600 text-white" title="Supprimer"><i class="fas fa-trash-alt"></i></button>
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
    <script>
        const sidebar = document.querySelector('aside.admin-sidebar');
        const mobileMenuButton = document.getElementById('mobile-menu-button'); // Assurez-vous que ce bouton existe dans admin_header_layout.php
        if (mobileMenuButton && sidebar) {
            mobileMenuButton.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        function toggleProductFormVisibility(show, isNew = false) {
            const formContainer = document.getElementById('productFormContainer');
            if (show) {
                formContainer.classList.remove('hidden');
                if (isNew) {
                    // Réinitialiser le formulaire pour un nouveau produit
                    formContainer.querySelector('h2').textContent = 'Ajouter un Nouveau Produit';
                    const form = formContainer.querySelector('form');
                    form.reset(); // Réinitialise les champs standards
                    // Vider les champs cachés et spécifiques
                    if (form.querySelector('input[name="product_id"]')) {
                        form.querySelector('input[name="product_id"]').remove();
                    }
                    form.querySelector('input[name="current_image_url"]').value = '';
                    const imgPreview = form.querySelector('img[alt="Image actuelle"]');
                    if (imgPreview) imgPreview.parentElement.classList.add('hidden'); // Cacher la prévisualisation
                    
                    // S'assurer que les valeurs par défaut sont bien mises
                    form.querySelector('#stock').value = 0;
                    form.querySelector('#est_actif').checked = true;

                }
                formContainer.scrollIntoView({ behavior: 'smooth' });
            } else {
                formContainer.classList.add('hidden');
                 // Optionnel: rediriger pour nettoyer l'URL si on était en mode édition
                if (window.location.href.includes('edit_id=')) {
                    window.location.href = 'admin_manage_products.php';
                }
            }
        }
        // Si le formulaire est affiché à cause d'une erreur de validation en POST ou en mode édition via GET, le laisser visible
        <?php if (($edit_mode || (isset($_POST['save_product']) && $feedback_type === 'error'))): ?>
            document.addEventListener('DOMContentLoaded', function() {
                toggleProductFormVisibility(true);
            });
        <?php endif; ?>
    </script>
</body>
</html>
