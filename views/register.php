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
<body style="background: #f9f9fa;">
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                <div class="card" style="border: none; border-radius: 20px;">
                    <div class="card-body" style="padding: 40px;">
                        <div class="text-center mb-4">
                            <div style="width: 64px; height: 64px; background: #1c1c1c; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                                </svg>
                            </div>
                            <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">No9ati</h1>
                            <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Créez votre compte enseignant</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 12px; padding: 16px;">
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="index.php?page=register">
                            <div class="mb-3">
                                <label for="nom" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Nom complet</label>
                                <input type="text" id="nom" name="nom" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" 
                                       placeholder="Votre nom complet" required
                                       style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="votre@email.com" required
                                       style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Mot de passe</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Au moins 6 caractères" required
                                       style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Confirmer le mot de passe</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       placeholder="Répétez votre mot de passe" required
                                       style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                                    Créer mon compte
                                </button>
                            </div>
                        </form>
                        
                        <hr style="border-color: rgba(0,0,0,0.1); margin: 24px 0;">
                        
                        <div class="text-center">
                            <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Déjà un compte ? 
                                <a href="index.php?page=login" style="color: #1c1c1c; font-weight: 500; text-decoration: none;">
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