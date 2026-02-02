<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="No9ati">
    <meta name="mobile-web-app-capable" content="yes">
    
    <title>Connexion - No9ati</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Icons -->
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.svg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php
require_once 'config/database.php';
require_once 'classes/Teacher.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $teacher = new Teacher($db);
    $teacher->email = $_POST['email'] ?? '';
    $teacher->mot_de_passe = $_POST['password'] ?? '';
    
    if (empty($teacher->email) || empty($teacher->mot_de_passe)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        if ($teacher->login()) {
            $_SESSION['teacher_id'] = $teacher->id;
            $_SESSION['teacher_name'] = $teacher->nom;
            $_SESSION['teacher_email'] = $teacher->email;
            
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - No9ati</title>
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
                            <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Connectez-vous à votre espace enseignant</p>
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
                        
                        <form method="POST" action="index.php?page=login">
                            <div class="mb-3">
                                <label for="email" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       placeholder="votre@email.com" required
                                       style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Mot de passe</label>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Votre mot de passe" required
                                       style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                                    Se connecter
                                </button>
                            </div>
                        </form>
                        
                        <hr style="border-color: rgba(0,0,0,0.1); margin: 24px 0;">
                        
                        <div class="text-center">
                            <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Pas encore de compte ? 
                                <a href="index.php?page=register" style="color: #1c1c1c; font-weight: 500; text-decoration: none;">
                                    Créer un compte
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
    <script src="assets/js/pwa.js"></script>
    <script>
        // Initialize PWA features on login page
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof PWAManager !== 'undefined') {
                window.pwaManager = new PWAManager();
            }
        });
    </script>
</body>
</html>