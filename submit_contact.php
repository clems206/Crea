<?php
// submit_contact.php

// Inclure la configuration de la base de données et la fonction de connexion
require_once 'includes/db_config.php'; // Assurez-vous que db_config.php est dans le dossier includes

// --- Configuration pour les emails (à adapter) ---
$admin_email_to = 'contact@creamod3d.fr';
// Assurez-vous que cette adresse est valide et autorisée à envoyer des emails depuis votre serveur Hostinger
$email_from_address = 'nepasrepondre@votredomaine.fr'; // Ex: noreply@creamod3d.fr
$email_from_name = 'CréaMod3D Formulaire de Contact';

// --- Initialisation des variables pour les messages de retour ---
$message_status = ''; // 'success' ou 'error'
$message_text = '';   // Le message à afficher

// Vérifier si le formulaire a été soumis et si le bouton submit_contact_form a été cliqué
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_contact_form'])) {

    // --- Récupération et nettoyage des données du formulaire ---
    $contact_nom = htmlspecialchars(trim($_POST['contact_nom'] ?? ''));
    $contact_email = filter_var(trim($_POST['contact_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contact_sujet = htmlspecialchars(trim($_POST['contact_sujet'] ?? ''));
    $contact_message = htmlspecialchars(trim($_POST['contact_message'] ?? ''));

    // --- Validation côté serveur ---
    $errors = [];
    if (empty($contact_nom)) $errors[] = "Le nom est requis.";
    if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'adresse email est invalide.";
    if (empty($contact_sujet)) $errors[] = "Le sujet est requis.";
    if (empty($contact_message)) $errors[] = "Le message ne peut pas être vide.";

    // --- Si pas d'erreurs de validation, procéder ---
    if (empty($errors)) {
        $pdo = connect_db(); // Tenter la connexion à la BDD

        if ($pdo) {
            try {
                // --- Insertion des données dans la base de données ---
                $sql = "INSERT INTO messages_contact (nom, email, sujet, message, date_soumission) 
                        VALUES (:nom, :email, :sujet, :message, NOW())";
                
                $stmt = $pdo->prepare($sql);

                // Liaison des paramètres
                $stmt->bindParam(':nom', $contact_nom);
                $stmt->bindParam(':email', $contact_email);
                $stmt->bindParam(':sujet', $contact_sujet);
                $stmt->bindParam(':message', $contact_message);

                $stmt->execute();
                $last_insert_id = $pdo->lastInsertId();

                // --- Préparation pour l'envoi d'email à l'administrateur ---
                $admin_email_subject_raw = "Nouveau message de contact CréaMod3D: " . $contact_sujet;
                $admin_email_subject_encoded = "=?UTF-8?B?".base64_encode($admin_email_subject_raw)."?="; // Encodage pour l'objet

                $admin_body_html = "<p>Vous avez reçu un nouveau message via le formulaire de contact de CréaMod3D :</p>";
                $admin_body_html .= "<ul>";
                $admin_body_html .= "<li><strong>De:</strong> " . htmlspecialchars($contact_nom) . "</li>";
                $admin_body_html .= "<li><strong>Email:</strong> <a href='mailto:" . htmlspecialchars($contact_email) . "'>" . htmlspecialchars($contact_email) . "</a></li>";
                $admin_body_html .= "<li><strong>Sujet:</strong> " . htmlspecialchars($contact_sujet) . "</li>";
                $admin_body_html .= "</ul>";
                $admin_body_html .= "<h4>Message :</h4>";
                $admin_body_html .= "<p style='padding:10px; border:1px solid #eee; background-color:#f9f9f9;'>" . nl2br(htmlspecialchars($contact_message)) . "</p>";
                $admin_body_html .= "<p><em>ID du message dans la base de données : $last_insert_id</em></p>";


                $admin_headers = "MIME-Version: 1.0" . "\r\n";
                $admin_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $admin_headers .= "From: \"$email_from_name\" <$email_from_address>\r\n";
                $admin_headers .= "Reply-To: \"" . htmlspecialchars($contact_nom) . "\" <" . htmlspecialchars($contact_email) . ">\r\n";
                $admin_headers .= "X-Mailer: PHP/" . phpversion();

                // mail($admin_email_to, $admin_email_subject_encoded, $admin_body_html, $admin_headers); // Décommentez pour l'envoi réel

                $message_status = 'success';
                $message_text = 'Votre message a été envoyé avec succès ! Nous vous répondrons dès que possible.';

            } catch (PDOException $e) {
                error_log("Erreur PDO lors de l'insertion du message de contact: " . $e->getMessage() . " | Data: " . json_encode($_POST));
                $message_status = 'error';
                $message_text = "Une erreur technique est survenue lors de l'envoi de votre message. Veuillez réessayer ou nous contacter directement. Code: DB_CONTACT_INSERT_FAIL";
            } finally {
                $pdo = null; // Fermer la connexion
            }
        } else {
            $message_status = 'error';
            $message_text = "Impossible d'établir une connexion à la base de données pour enregistrer votre message. Veuillez contacter l'administrateur. Code: DB_CONNECT_FAIL";
        }
    } else {
        // Erreurs de validation du formulaire
        $message_status = 'error';
        $message_text = "Veuillez corriger les erreurs suivantes :<br><ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
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
$redirect_url = 'index.php#contact?contact_status=' . urlencode($message_status) . '&message=' . urlencode($message_text);
header('Location: ' . $redirect_url);
exit;

?>
