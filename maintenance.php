<?php
// maintenance.php
// Page affichée lorsque le site est en mode maintenance.

// Vous pouvez récupérer l'email de contact depuis la BDD si vous le souhaitez,
// mais pour une page de maintenance simple, un email codé en dur peut suffire
// ou vous pouvez le rendre configurable via un autre paramètre dans site_settings.
$contact_email_maintenance = "contact@creamod3d.fr"; // Email par défaut

// Il est préférable de ne pas inclure le header/footer standard pour une page de maintenance
// afin d'éviter des dépendances qui pourraient être affectées par la maintenance.
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CréaMod3D - Site en Maintenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/images/logo.png" type="image/png"> <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F9FAFB; /* Gris clair */
            color: #1F2937; /* Gris foncé */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            padding: 1rem;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            background-color: #FFFFFF;
            padding: 2rem 2.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }
        img.logo {
            max-height: 80px;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 1.875rem; /* text-3xl */
            font-weight: 700; /* font-bold */
            color: #2D2D2D; /* Couleur primaire foncée du logo */
            margin-bottom: 0.75rem;
        }
        p {
            font-size: 1rem; /* text-base */
            color: #4B5563; /* Gris moyen */
            margin-bottom: 1.5rem;
        }
        .contact-info {
            font-size: 0.875rem; /* text-sm */
            color: #6B7280; /* Gris plus clair */
        }
        .contact-info a {
            color: #A8C63F; /* Couleur accent du logo */
            text-decoration: none;
        }
        .contact-info a:hover {
            text-decoration: underline;
        }
        .icon {
            font-size: 3rem; /* text-5xl */
            color: #A8C63F;
            margin-bottom: 1rem;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="container">
        <img src="assets/images/logo.png" alt="Logo CréaMod3D" class="logo" onerror="this.style.display='none';">
        <div class="icon">
            <i class="fas fa-tools"></i> </div>
        <h1>Site en Maintenance</h1>
        <p>
            CréaMod3D est actuellement en cours de maintenance pour améliorer votre expérience.
            Nous serons de retour très prochainement !
        </p>
        <p>
            Merci de votre patience et de votre compréhension.
        </p>
        <div class="contact-info">
            Si vous avez une question urgente, vous pouvez nous contacter à l'adresse :
            <a href="mailto:<?php echo htmlspecialchars($contact_email_maintenance); ?>"><?php echo htmlspecialchars($contact_email_maintenance); ?></a>
        </div>
    </div>
</body>
</html>
