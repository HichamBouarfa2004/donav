<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/Teacher.php';
$database = new Database();
$db = $database->getConnection();
$teacher = new Teacher($db);
$teacher->getById($_SESSION['teacher_id']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $teacher->nom = $_POST['nom'];
        $teacher->email = $_POST['email'];
        if ($teacher->update()) {
            $_SESSION['teacher_name'] = $teacher->nom;
            $_SESSION['teacher_email'] = $teacher->email;
            $success = 'Profil mis à jour avec succès!';
        } else {
            $error = 'Erreur lors de la mise à jour.';
        }
    } elseif (isset($_POST['update_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        if ($new_password === $confirm_password && !empty($new_password)) {
            if ($teacher->updatePassword($new_password)) {
                $success = 'Mot de passe mis à jour!';
            } else {
                $error = 'Erreur lors de la mise à jour du mot de passe.';
            }
        } else {
            $error = 'Les mots de passe ne correspondent pas ou sont vides.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="padding-top: 88px;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <button class="btn d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Mon Profil</h1>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 28px;">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Mon Profil</h1>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Gérez vos informations personnelles et paramètres de compte</p>
                </div>
            </div>

            <?php if ($error): ?>
                <script>var _toastError = <?= json_encode($error) ?>;</script>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <script>var _toastSuccess = <?= json_encode($success) ?>;</script>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Information -->
                <div class="col-12 col-lg-6">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Informations personnelles</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="nom" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Nom complet</label>
                                    <input type="text" id="nom" name="nom" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher->nom); ?>" required
                                           style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                </div>

                                <div class="mb-3">
                                    <label for="email" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Adresse email</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher->email); ?>" required
                                           style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                </div>

                                <!-- Activity titles are now managed per-class and selected during certificate generation -->

                                <div class="d-grid">
                                    <button type="submit" name="update_profile" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                                        <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                        Mettre à jour le profil
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="col-12 col-lg-6">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Changer le mot de passe</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="new_password" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Nouveau mot de passe</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" 
                                           placeholder="Minimum 6 caractères" required
                                           style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Confirmer le mot de passe</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           placeholder="Répétez le nouveau mot de passe" required
                                           style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="update_password" class="btn" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                                        <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        Changer le mot de passe
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Informations du compte</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <div class="row">
                                <div class="col-12 col-md-4">
                                    <h6 style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; margin-bottom: 4px;">Nom d'utilisateur</h6>
                                    <p style="font-size: 14px; color: #1c1c1c; margin-bottom: 16px;"><?php echo htmlspecialchars($teacher->nom); ?></p>
                                </div>
                                <div class="col-12 col-md-4">
                                    <h6 style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; margin-bottom: 4px;">Email</h6>
                                    <p style="font-size: 14px; color: #1c1c1c; margin-bottom: 16px;"><?php echo htmlspecialchars($teacher->email); ?></p>
                                </div>
                                <div class="col-12 col-md-4">
                                    <h6 style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; margin-bottom: 4px;">Activité</h6>
                                    <p style="font-size: 14px; color: #1c1c1c; margin-bottom: 16px;">Gérée par classe</p>
                                </div>
                            </div>
                            <hr style="border-color: rgba(0,0,0,0.1);">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="index.php?page=dashboard" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                    <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                    Tableau de bord
                                </a>
                                <a href="index.php?page=manage_classes" class="btn" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                    <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    Gérer les classes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
