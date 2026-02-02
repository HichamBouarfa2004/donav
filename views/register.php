<?php
require_once 'config/database.php';
require_once 'classes/Teacher.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $teacher = new Teacher($db);
    $teacher->nom = $_POST['nom'] ?? '';
    $teacher->email = $_POST['email'] ?? '';
    $teacher->mot_de_passe = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($teacher->nom) || empty($teacher->email) || empty($teacher->mot_de_passe) || empty($confirm_password)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif ($teacher->mot_de_passe !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($teacher->mot_de_passe) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        if ($teacher->register()) {
            $success = 'Compte créé avec succès! Vous pouvez maintenant vous connecter.';
        } else {
            $error = 'Erreur lors de la création du compte. L\'email existe peut-être déjà.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                <div class="card border-0 shadow">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <div class="fs-1 mb-3">📚</div>
                            <h1 class="h3 fw-bold text-primary">No9ati</h1>
                            <p class="text-muted mb-0">Créez votre compte enseignant</p>
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
                        
                        <form method="POST" action="index.php?page=register">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom complet</label>
                                <input type="text" id="nom" name="nom" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                                       placeholder="Votre nom complet" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="votre@email.com" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Au moins 6 caractères" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       placeholder="Répétez votre mot de passe" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Créer mon compte
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Déjà un compte ? 
                                <a href="index.php?page=login" class="text-decoration-none">
                                    Se connecter
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>