<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);

$message = '';
$error = '';
$selected_class_id = $_GET['class_id'] ?? 0;

// Handle student creation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student->nom = $_POST['nom'] ?? '';
    $student->classe_id = $_POST['classe_id'] ?? 0;
    $student->points = 0;
    
    if (empty($student->nom)) {
        $error = 'Le nom de l\'élève est requis.';
    } elseif (empty($student->classe_id)) {
        $error = 'Veuillez sélectionner une classe.';
    } else {
        // Verify class belongs to teacher
        if ($classroom->getById($student->classe_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
            // Check if student already exists in this class
            if ($student->studentExists($student->nom, $student->classe_id)) {
                $error = 'Un élève avec ce nom existe déjà dans cette classe.';
            } elseif ($student->create()) {
                $message = 'Élève ajouté avec succès!';
                $selected_class_id = $student->classe_id; // Keep the same class selected
            } else {
                $error = 'Erreur lors de l\'ajout de l\'élève.';
            }
        } else {
            $error = 'Classe non valide.';
        }
    }
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un élève - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Ajouter un élève</h1>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <main class="container-fluid" style="padding: 28px;">
            <div class="row mb-4 align-items-center">
                <div class="col-12 col-md-8">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 0;">Ajouter un élève</h1>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="index.php?page=dashboard" class="btn" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                        <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Retour
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- Add Student Form -->
                <div class="col-12 col-lg-8">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Informations de l'élève</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($classes)): ?>
                                <div class="d-flex align-items-center" role="alert" style="background: #fef4e6; border-radius: 12px; padding: 16px;">
                                    <div>
                                        <h6 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Aucune classe disponible!</h6>
                                        <p style="font-size: 14px; color: rgba(0,0,0,0.6); margin-bottom: 12px;">Vous devez d'abord créer une classe avant d'ajouter des élèves.</p>
                                        <a href="index.php?page=manage_classes" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                            Créer une classe
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="nom" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Nom complet de l'élève</label>
                                        <input type="text" name="nom" id="nom" class="form-control" 
                                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                               placeholder="Prénom Nom" required
                                               style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="classe_id" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Classe</label>
                                        <select name="classe_id" id="classe_id" class="form-select" required
                                                style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                            <option value="">Sélectionner une classe</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?php echo $class['id']; ?>" 
                                                        <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['nom']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500;">
                                            <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="8.5" cy="7" r="4"></circle>
                                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                                <line x1="23" y1="11" x2="17" y2="11"></line>
                                            </svg>
                                            Ajouter l'élève
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-12 col-lg-4">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Actions rapides</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <div class="d-grid gap-3">
                                <a href="index.php?page=import_students&class_id=<?php echo $selected_class_id; ?>" class="btn d-flex align-items-center justify-content-center" style="background: #edeefc; color: #6366f1; border: none; border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                    <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="17 8 12 3 7 8"></polyline>
                                        <line x1="12" y1="3" x2="12" y2="15"></line>
                                    </svg>
                                    Importer depuis Excel
                                </a>
                                
                                <a href="index.php?page=manage_classes" class="btn d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                    <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    Gérer les classes
                                </a>
                                
                                <a href="index.php?page=dashboard" class="btn d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                    <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                    Tableau de bord
                                </a>
                            </div>
                            
                            <hr style="border-color: rgba(0,0,0,0.1); margin: 24px 0;">
                            
                            <div>
                                <h6 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 12px; display: flex; align-items: center;">
                                    <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    Conseils
                                </h6>
                                <ul class="list-unstyled" style="font-size: 12px; color: rgba(0,0,0,0.6);">
                                    <li class="mb-2">• Utilisez le format "Prénom Nom" pour une meilleure lisibilité</li>
                                    <li class="mb-2">• Vous pouvez importer plusieurs élèves à la fois avec un fichier Excel</li>
                                    <li class="mb-2">• Les élèves commencent avec 0 points</li>
                                    <li class="mb-0">• Un certificat est généré automatiquement à 100 points</li>
                                </ul>
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