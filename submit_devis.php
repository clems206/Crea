<?php
// submit_devis.php

// Démarrer la session si vous prévoyez de l'utiliser (par exemple, pour des messages flash plus avancés)
// session_start();

// Inclure la configuration de la base de données et la fonction de connexion
require_once 'includes/db_config.php';

// --- Configuration pour les emails (à adapter) ---
$admin_email_to = 'contact@creamod3d.fr';
// Assurez-vous que cette adresse est valide et autorisée à envoyer des emails depuis votre serveur Hostinger
$email_from_address = 'nepasrepondre@votredomaine.fr'; // Ex: noreply@creamod3d.fr
$email_from_name = 'CréaMod3D';

// --- Initialisation des variables pour les messages de retour ---
$message_status = ''; // 'success' ou 'error'
$message_text = '';   // Le message à afficher

// Vérifier si le formulaire a été soumis et si le bouton submit_devis a été cliqué
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_devis'])) {

    // --- Récupération et nettoyage des données du formulaire ---
    // Utiliser htmlspecialchars pour prévenir les attaques XSS
    // Utiliser trim pour supprimer les espaces en début et fin de chaîne
    $nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
    $prenom = htmlspecialchars(trim($_POST['prenom'] ?? ''));
    $email_client = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL); // Nettoyer l'email
    $telephone = htmlspecialchars(trim($_POST['telephone'] ?? ''));
    $type_projet = htmlspecialchars(trim($_POST['type_projet'] ?? ''));
    $description_projet = htmlspecialchars(trim($_POST['description_projet'] ?? ''));

    // Champs conditionnels (initialisation)
    $texte_personnalise = null; // Mettre à null par défaut si non applicable
    $couleur_contour = null;
    $eclairage = null;
    $option_rgb = null;
    // Le montant estimé sera recalculé côté serveur pour la sécurité
    // $montant_estime_client = floatval($_POST['montant_estime'] ?? 0); // On peut le récupérer pour info mais ne pas s'y fier pour l'enregistrement

    if ($type_projet === 'prenom_lumineux' || $type_projet === 'logo_lumineux') {
        $texte_personnalise = htmlspecialchars(trim($_POST['texte_personnalise'] ?? ''));
        $couleur_contour = htmlspecialchars(trim($_POST['couleur_contour'] ?? 'noir'));
        $eclairage = htmlspecialchars(trim($_POST['eclairage'] ?? 'blanc_chaud'));
        if ($eclairage === 'rgb') {
            $option_rgb = htmlspecialchars(trim($_POST['option_rgb'] ?? 'aucune'));
        }
    }

    // --- Validation côté serveur ---
    $errors = [];
    if (empty($nom)) $errors[] = "Le nom est requis.";
    if (empty($prenom)) $errors[] = "Le prénom est requis.";
    if (empty($email_client) || !filter_var($email_client, FILTER_VALIDATE_EMAIL)) $errors[] = "L'adresse email est invalide.";
    if (empty($type_projet)) $errors[] = "Le type de projet est requis.";
    if (empty($description_projet)) $errors[] = "La description du projet est requise.";

    if (($type_projet === 'prenom_lumineux' || $type_projet === 'logo_lumineux')) {
        if (empty($texte_personnalise)) {
            $errors[] = "Le texte personnalisé est requis pour ce type de projet.";
        } elseif (strlen($texte_personnalise) > 13) {
            $errors[] = "Le texte personnalisé ne doit pas dépasser 13 caractères.";
        } elseif (!preg_match('/^[a-zA-Z0-9\s]*$/', $texte_personnalise)) {
            $errors[] = "Le texte personnalisé ne doit contenir que des lettres, des chiffres et des espaces.";
        }
    }
    // Optionnel: valider le format du téléphone si fourni
    if (!empty($telephone) && !preg_match('/^[0-9\s\-\+]{10,15}$/', $telephone)) {
        $errors[] = "Le format du numéro de téléphone est invalide.";
    }

    // --- Recalcul du montant estimé (côté serveur - PLUS SÉCURISÉ) ---
    $montant_estime_serveur = 0.00;
    if ($type_projet === 'prenom_lumineux' || $type_projet === 'logo_lumineux') {
        if (!empty($texte_personnalise) && preg_match('/^[a-zA-Z0-9\s]*$/', $texte_personnalise) && strlen($texte_personnalise) <= 13) {
            $clean_text = preg_replace('/\s+/', '', $texte_personnalise); // Enlever les espaces pour le comptage
            $num_chars = strlen($clean_text);
            if ($num_chars > 0) {
                $montant_estime_serveur = 39.00; // Prix de base
                if ($num_chars > 3) {
                    $montant_estime_serveur += ($num_chars - 3) * 5.00;
                }
            }
            if ($eclairage === 'rgb') {
                if ($option_rgb === 'bluetooth') $montant_estime_serveur += 5.00;
                elseif ($option_rgb === 'wifi') $montant_estime_serveur += 8.00;
            }
        }
    }

    // --- Traitement du fichier uploadé ---
    $uploaded_file_server_path = null; // Chemin du fichier sur le serveur
    $original_file_name = null; // Nom original du fichier
    $file_upload_error_message = '';

    // Vérifier si un fichier a été envoyé et s'il n'y a pas d'erreur d'upload
    if (isset($_FILES['devis_fichier']) && $_FILES['devis_fichier']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/devis/'; // Assurez-vous que ce dossier existe et est accessible en écriture
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) { // Tenter de créer le dossier
                $errors[] = "Échec de la création du dossier d'upload.";
                $file_upload_error_message = "Erreur serveur : le dossier d'upload ne peut être créé.";
            }
        }

        if (empty($errors)) { // Continuer seulement si le dossier est prêt
            $original_file_name = basename($_FILES['devis_fichier']['name']);
            $file_extension = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['stl', 'obj', 'jpg', 'jpeg', 'png', 'pdf', 'zip', 'rar']; // Ajout de zip/rar
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (in_array($file_extension, $allowed_extensions)) {
                if ($_FILES['devis_fichier']['size'] <= $max_file_size) {
                    // Générer un nom de fichier unique pour éviter les écrasements et pour la sécurité
                    $safe_original_name = preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_file_name);
                    $new_file_name = uniqid('devisfile_', true) . '_' . $safe_original_name;
                    $uploaded_file_server_path = $upload_dir . $new_file_name;

                    if (!move_uploaded_file($_FILES['devis_fichier']['tmp_name'], $uploaded_file_server_path)) {
                        $errors[] = "Erreur lors du déplacement du fichier uploadé.";
                        $file_upload_error_message = "Erreur serveur lors de l'enregistrement du fichier.";
                    }
                } else {
                    $errors[] = "Le fichier '" . htmlspecialchars($original_file_name) . "' est trop volumineux (max 5MB).";
                    $file_upload_error_message = "Fichier trop volumineux.";
                }
            } else {
                $errors[] = "Type de fichier non autorisé pour '" . htmlspecialchars($original_file_name) . "'. Extensions autorisées : " . implode(', ', $allowed_extensions);
                $file_upload_error_message = "Type de fichier non valide.";
            }
        }
    } elseif (isset($_FILES['devis_fichier']) && $_FILES['devis_fichier']['error'] != UPLOAD_ERR_NO_FILE) {
        // Gérer les autres erreurs d'upload de fichier
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => "Le fichier téléchargé excède la directive upload_max_filesize dans php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "Le fichier téléchargé excède la directive MAX_FILE_SIZE spécifiée dans le formulaire HTML.",
            UPLOAD_ERR_PARTIAL    => "Le fichier n'a été que partiellement téléchargé.",
            UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture du fichier sur le disque.",
            UPLOAD_ERR_EXTENSION  => "Une extension PHP a arrêté l'envoi de fichier.",
        ];
        $error_code = $_FILES['devis_fichier']['error'];
        $errors[] = "Erreur lors de l'upload du fichier : " . ($upload_errors[$error_code] ?? "Erreur inconnue (code: $error_code).");
        $file_upload_error_message = "Erreur d'upload (code $error_code).";
    }


    // --- Si pas d'erreurs de validation, procéder à l'insertion en BDD et envoi d'emails ---
    if (empty($errors)) {
        $pdo = connect_db(); // Tenter la connexion à la BDD

        if ($pdo) {
            try {
                // --- Insertion des données dans la base de données ---
                $sql = "INSERT INTO demandes_devis 
                            (nom, prenom, email, telephone, type_projet, texte_personnalise, couleur_contour, eclairage, option_rgb, description_projet, montant_estime, fichier_upload_path, fichier_nom_original, date_soumission, statut) 
                        VALUES 
                            (:nom, :prenom, :email, :telephone, :type_projet, :texte_personnalise, :couleur_contour, :eclairage, :option_rgb, :description_projet, :montant_estime, :fichier_upload_path, :fichier_nom_original, NOW(), :statut)";
                
                $stmt = $pdo->prepare($sql);

                // Liaison des paramètres
                $statut_initial = 'En attente';
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':prenom', $prenom);
                $stmt->bindParam(':email', $email_client);
                $stmt->bindParam(':telephone', $telephone, !empty($telephone) ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':type_projet', $type_projet);
                $stmt->bindParam(':texte_personnalise', $texte_personnalise, $texte_personnalise !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':couleur_contour', $couleur_contour, $couleur_contour !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':eclairage', $eclairage, $eclairage !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':option_rgb', $option_rgb, $option_rgb !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':description_projet', $description_projet);
                $stmt->bindParam(':montant_estime', $montant_estime_serveur);
                $stmt->bindParam(':fichier_upload_path', $uploaded_file_server_path, $uploaded_file_server_path !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':fichier_nom_original', $original_file_name, $original_file_name !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindParam(':statut', $statut_initial);

                $stmt->execute();
                $last_insert_id = $pdo->lastInsertId(); // Récupérer l'ID de la demande de devis

                // --- Préparation pour l'envoi d'email à l'administrateur ---
                $admin_subject = "Nouvelle demande de devis CréaMod3D - $prenom $nom (ID: $last_insert_id)";
                $admin_body_html = "<p>Une nouvelle demande de devis a été soumise :</p><ul>";
                $admin_body_html .= "<li><strong>ID Demande:</strong> $last_insert_id</li>";
                $admin_body_html .= "<li><strong>Nom:</strong> $prenom $nom</li>";
                $admin_body_html .= "<li><strong>Email:</strong> <a href='mailto:$email_client'>$email_client</a></li>";
                if (!empty($telephone)) $admin_body_html .= "<li><strong>Téléphone:</strong> $telephone</li>";
                $admin_body_html .= "<li><strong>Type de projet:</strong> $type_projet</li>";
                if ($type_projet === 'prenom_lumineux' || $type_projet === 'logo_lumineux') {
                    $admin_body_html .= "<li><strong>Texte personnalisé:</strong> $texte_personnalise</li>";
                    $admin_body_html .= "<li><strong>Couleur contour:</strong> $couleur_contour</li>";
                    $admin_body_html .= "<li><strong>Éclairage:</strong> $eclairage</li>";
                    if ($eclairage === 'rgb' && $option_rgb) {
                        $admin_body_html .= "<li><strong>Option RGB:</strong> $option_rgb</li>";
                    }
                }
                $admin_body_html .= "<li><strong>Description:</strong><br>" . nl2br($description_projet) . "</li>";
                $admin_body_html .= "<li><strong>Montant estimé (calculé serveur):</strong> " . number_format($montant_estime_serveur, 2, ',', ' ') . " €</li>";
                if ($uploaded_file_server_path && $original_file_name) {
                    $admin_body_html .= "<li><strong>Fichier joint:</strong> " . htmlspecialchars($original_file_name) . " (Chemin serveur: $uploaded_file_server_path)</li>";
                    // Pour un lien de téléchargement direct, il faudrait un script sécurisé côté admin.
                } elseif ($file_upload_error_message) {
                     $admin_body_html .= "<li><strong>Statut fichier:</strong> " . htmlspecialchars($file_upload_error_message) . "</li>";
                }
                $admin_body_html .= "</ul><p>Vous pouvez consulter cette demande dans votre interface d'administration.</p>";

                $admin_headers = "MIME-Version: 1.0" . "\r\n";
                $admin_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $admin_headers .= "From: \"$email_from_name\" <$email_from_address>\r\n";
                $admin_headers .= "Reply-To: \"$prenom $nom\" <$email_client>\r\n";
                $admin_headers .= "X-Mailer: PHP/" . phpversion();

                // mail($admin_email_to, "=?UTF-8?B?".base64_encode($admin_subject)."?=", $admin_body_html, $admin_headers); // Décommentez pour l'envoi réel

                // --- Préparation pour l'envoi d'email de confirmation au client ---
                $client_subject = "Votre demande de devis chez CréaMod3D a bien été reçue (ID: $last_insert_id)";
                $client_body_html = "<p>Bonjour $prenom,</p>";
                $client_body_html .= "<p>Nous avons bien reçu votre demande de devis (numéro de suivi : <strong>$last_insert_id</strong>) et nous vous en remercions. Nous l'étudierons attentivement et nous vous répondrons dans les plus brefs délais.</p>";
                $client_body_html .= "<p><strong>Récapitulatif de votre demande :</strong></p><ul>";
                $client_body_html .= "<li>Type de projet: $type_projet</li>";
                if ($type_projet === 'prenom_lumineux' || $type_projet === 'logo_lumineux') {
                     $client_body_html .= "<li>Texte personnalisé: $texte_personnalise</li>";
                }
                $client_body_html .= "<li>Description: " . nl2br(htmlspecialchars(substr($description_projet, 0, 150))) . "...</li>"; // Extrait sécurisé
                $client_body_html .= "</ul>";
                $client_body_html .= "<p>Si vous avez joint un fichier, il a également été reçu.</p>";
                $client_body_html .= "<p>Cordialement,<br>L'équipe CréaMod3D</p>";
                
                $client_headers = "MIME-Version: 1.0" . "\r\n";
                $client_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $client_headers .= "From: \"$email_from_name\" <$email_from_address>\r\n";
                $client_headers .= "Reply-To: \"$email_from_name\" <$admin_email_to>\r\n"; // L'admin pour les réponses
                $client_headers .= "X-Mailer: PHP/" . phpversion();

                // mail($email_client, "=?UTF-8?B?".base64_encode($client_subject)."?=", $client_body_html, $client_headers); // Décommentez pour l'envoi réel

                $message_status = 'success';
                $message_text = 'Votre demande de devis (ID: ' . $last_insert_id . ') a été envoyée avec succès ! Nous vous contacterons bientôt.';
                if ($file_upload_error_message && $uploaded_file_server_path == null) { // Si une erreur d'upload mais le reste OK
                    $message_text .= " Note: Il y a eu un problème avec le fichier joint: " . htmlspecialchars($file_upload_error_message);
                }


            } catch (PDOException $e) {
                // Gérer les erreurs de base de données
                error_log("Erreur PDO lors de l'insertion du devis: " . $e->getMessage() . " | Data: " . json_encode($_POST)); // Log pour le dev
                $message_status = 'error';
                // Ne pas afficher $e->getMessage() en production directement à l'utilisateur.
                $message_text = "Une erreur technique est survenue lors de la soumission de votre devis. Veuillez réessayer ou nous contacter directement. Code: DB_INSERT_FAIL";
            } finally {
                $pdo = null; // Fermer la connexion
            }
        } else {
            // La connexion à la BDD a échoué (géré dans connect_db, mais double check)
            $message_status = 'error';
            $message_text = "Impossible d'établir une connexion à la base de données pour enregistrer votre devis. Veuillez contacter l'administrateur. Code: DB_CONNECT_FAIL";
        }
    } else {
        // Erreurs de validation du formulaire
        $message_status = 'error';
        $message_text = "Veuillez corriger les erreurs suivantes :<br><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
        if ($file_upload_error_message) {
             $message_text .= "<br>Concernant le fichier : " . htmlspecialchars($file_upload_error_message);
        }
    }
} else {
    // Accès direct au script sans soumission POST ou bouton manquant
    $message_status = 'error';
    $message_text = 'Aucune donnée de formulaire reçue ou action non autorisée.';
    // Rediriger vers la page d'accueil si l'accès est direct
    header('Location: index.php');
    exit;
}

// --- Redirection vers la page principale avec le message de statut ---
// Utiliser des sessions pour les messages flash est plus robuste que les paramètres GET pour les longs messages HTML.
// Pour l'instant, on continue avec GET, mais attention à la longueur de l'URL.
$redirect_url = 'index.php#devis?quote_status=' . urlencode($message_status) . '&message=' . urlencode($message_text);
header('Location: ' . $redirect_url);
exit;

?>
