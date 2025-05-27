<?php
// admin_login.php
session_start(); // Démarrer la session PHP au tout début du script

// Inclure la configuration de la base de données
require_once 'includes/db_config.php';

// Rediriger vers le tableau de bord si l'administrateur est déjà connecté
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit;
}

$error_message = '';

// Traitement du formulaire de connexion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_admin'])) {
    $submitted_username = trim($_POST['username'] ?? '');
    $submitted_password = trim($_POST['password'] ?? '');

    if (empty($submitted_username) || empty($submitted_password)) {
        $error_message = "Veuillez saisir votre nom d'utilisateur et votre mot de passe.";
    } else {
        $pdo = connect_db(); // Fonction de connexion depuis db_config.php

        if ($pdo) {
            try {
                $sql = "SELECT username, password_hash FROM administrateurs WHERE username = :username LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $submitted_username);
                $stmt->execute();

                $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin_user && password_verify($submitted_password, $admin_user['password_hash'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $admin_user['username'];
                    session_regenerate_id(true);

                    header('Location: admin_dashboard.php');
                    exit;
                } else {
                    $error_message = "Nom d'utilisateur ou mot de passe incorrect.";
                }

            } catch (PDOException $e) {
                error_log("Erreur de connexion admin: " . $e->getMessage());
                $error_message = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
            } finally {
                $pdo = null;
            }
        } else {
            $error_message = "Erreur de connexion au serveur. Veuillez réessayer plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur - CréaMod3D</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="assets/images/logo.png" type="image/png">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb; /* bg-gray-50 */
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); /* shadow-xl */
            width: 100%;
            max-width: 28rem; /* max-w-md */
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db; /* border-gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            box-shadow: inset 0 1px 2px 0 rgba(0,0,0,0.05); /* shadow-sm */
        }
        .form-input:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #A8C63F; 
            box-shadow: 0 0 0 3px rgba(168, 198, 63, 0.3);
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle-icon {
            position: absolute;
            top: 50%;
            right: 0.75rem; /* pr-3 */
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280; /* text-gray-500 */
        }
        .password-toggle-icon:hover {
            color: #4b5563; /* text-gray-600 */
        }
        .btn-submit {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: #2D2D2D; 
            color: white;
            font-weight: 600; 
            border-radius: 0.375rem; 
            border: 1px solid transparent;
            transition: background-color 0.2s;
        }
        .btn-submit:hover {
            background-color: #4a4a4a; 
        }
        .error-message {
            background-color: #fee2e2; 
            color: #b91c1c; 
            padding: 0.75rem;
            border: 1px solid #fecaca; 
            border-radius: 0.375rem; 
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="mb-8 text-center">
            <a href="index.php">
                <img src="assets/images/logo.png" alt="Logo CréaMod3D" class="h-16 w-auto mx-auto" onerror="this.style.display='none';">
            </a>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900">Accès Administrateur</h1>
        </div>

        <div class="login-card">
            <form action="admin_login.php" method="POST" class="space-y-6">
                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="username" class="block text-sm font-medium leading-6 text-gray-900">Nom d'utilisateur ou Email</label>
                    <div class="mt-2">
                        <input id="username" name="username" type="text" autocomplete="username" required class="form-input" placeholder="admin">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Mot de passe</label>
                    <div class="mt-2 password-wrapper">
                        <input id="password" name="password" type="password" autocomplete="current-password" required class="form-input pr-10">
                        <span class="password-toggle-icon" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div>
                    <button type="submit" name="login_admin" class="btn-submit">
                        Se connecter
                    </button>
                </div>
            </form>

            <p class="mt-10 text-center text-sm text-gray-500">
                Retourner au <a href="index.php" class="font-semibold leading-6" style="color: #A8C63F; hover:text-decoration:underline;">site principal</a>
            </p>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.password-toggle-icon i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
