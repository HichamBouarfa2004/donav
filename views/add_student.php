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
            if ($student->create()) {
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
<body class="bg-light">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 250px; padding-top: 70px; min-height: 100vh;">
        <?php include 'views/partials/header.php'; ?>
        
        <main class="container-fluid py-4">
            <div class="row mb-4 align-items-center">
                <div class="col-12 col-md-8">
                    <h1 class="h3 mb-0">Ajouter un élève</h1>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="index.php?page=dashboard" class="btn btn-outline-secondary">
                        Retour au tableau de bord
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- Add Student Form -->
                <div class="col-12 col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Informations de l'élève</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($classes)): ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <div>
                                        <h6 class="alert-heading">Aucune classe disponible!</h6>
                                        <p class="mb-3">Vous devez d'abord créer une classe avant d'ajouter des élèves.</p>
                                        <a href="index.php?page=manage_classes" class="btn btn-primary">
                                            Créer une classe
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="nom" class="form-label">Nom complet de l'élève</label>
                                        <input type="text" name="nom" id="nom" class="form-control form-control-lg" 
                                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                               placeholder="Prénom Nom" required>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="classe_id" class="form-label">Classe</label>
                                        <select name="classe_id" id="classe_id" class="form-select form-select-lg" required>
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
                                        <button type="submit" class="btn btn-primary btn-lg">
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
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Actions rapides</h2>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <a href="index.php?page=import_students&class_id=<?php echo $selected_class_id; ?>" class="btn btn-secondary btn-lg">
                                    📁 Importer depuis Excel
                                </a>
                                
                                <a href="index.php?page=manage_classes" class="btn btn-outline-secondary btn-lg">
                                    🏫 Gérer les classes
                                </a>
                                
                                <a href="index.php?page=dashboard" class="btn btn-outline-secondary btn-lg">
                                    🏠 Tableau de bord
                                </a>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div>
                                <h6 class="fw-semibold mb-3">💡 Conseils</h6>
                                <ul class="list-unstyled small text-muted">
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