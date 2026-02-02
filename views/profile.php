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
<body class="bg-light">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 250px; padding-top: 70px; min-height: 100vh;">
        <?php include 'views/partials/header.php'; ?>

        <main class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 mb-1">Mon Profil</h1>
                    <p class="text-muted mb-0">Gérez vos informations personnelles et paramètres de compte</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Information -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Informations personnelles</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom complet</label>
                                    <input type="text" id="nom" name="nom" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher->nom); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Adresse email</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($teacher->email); ?>" required>
                                </div>

                                <!-- Activity titles are now managed per-class and selected during certificate generation -->

                                <div class="d-grid">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        Mettre à jour le profil
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Changer le mot de passe</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" 
                                           placeholder="Minimum 6 caractères" required>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           placeholder="Répétez le nouveau mot de passe" required>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="update_password" class="btn btn-outline-secondary">
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
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Informations du compte</h2>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-md-4">
                                    <h6 class="text-muted mb-1">Nom d'utilisateur</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($teacher->nom); ?></p>
                                </div>
                                <div class="col-12 col-md-4">
                                    <h6 class="text-muted mb-1">Email</h6>
                                    <p class="mb-3"><?php echo htmlspecialchars($teacher->email); ?></p>
                                </div>
                                <div class="col-12 col-md-4">
                                    <h6 class="text-muted mb-1">Activité</h6>
                                        <p class="mb-3">Gérée par classe</p>
                                    </div>
                            </div>
                            <hr>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="index.php?page=dashboard" class="btn btn-outline-primary">
                                    Retour au tableau de bord
                                </a>
                                <a href="index.php?page=manage_classes" class="btn btn-outline-secondary">
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
