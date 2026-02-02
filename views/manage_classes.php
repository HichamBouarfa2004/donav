<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);

$message = '';
$error = '';

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_class'])) {
    $classroom->nom = $_POST['nom'] ?? '';
    $classroom->enseignant_id = $_SESSION['teacher_id'];
    
    if (empty($classroom->nom)) {
        $error = 'Le nom de la classe est requis.';
    } elseif ($classroom->classNameExists($classroom->nom, $classroom->enseignant_id)) {
        $error = 'Une classe avec ce nom existe déjà.';
    } else {
        if ($classroom->create()) {
            $message = 'Classe créée avec succès!';
        } else {
            $error = 'Erreur lors de la création de la classe.';
        }
    }
}

// Handle class deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_class'])) {
    $classroom->id = $_POST['class_id'] ?? 0;
    $classroom->enseignant_id = $_SESSION['teacher_id'];
    
    if ($classroom->delete()) {
        $message = 'Classe supprimée avec succès!';
    } else {
        $error = 'Erreur lors de la suppression de la classe.';
    }
}

// Handle class update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_class'])) {
    $classroom->id = $_POST['class_id'] ?? 0;
    $classroom->nom = $_POST['nom'] ?? '';
    $classroom->enseignant_id = $_SESSION['teacher_id'];
    
    if (empty($classroom->nom)) {
        $error = 'Le nom de la classe est requis.';
    } elseif ($classroom->classNameExists($classroom->nom, $classroom->enseignant_id, $classroom->id)) {
        $error = 'Une classe avec ce nom existe déjà.';
    } else {
        if ($classroom->update()) {
            $message = 'Classe modifiée avec succès!';
        } else {
            $error = 'Erreur lors de la modification de la classe.';
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
    <title>Gestion des classes - No9ati</title>
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
                    <h1 class="h3 mb-0">Gestion des classes</h1>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClassModal">
                        Créer une classe
                    </button>
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
            
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h2 class="h5 mb-0">Mes Classes</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($classes)): ?>
                                <div class="text-center py-5">
                                    <p class="text-muted mb-3">Vous n'avez pas encore de classes.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClassModal">
                                        Créer votre première classe
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="classesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nom de la classe</th>
                                                <th class="text-center">Nombre d'élèves</th>
                                                <th class="text-center">Points totaux</th>
                                                <th class="text-center">Date de création</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classes as $class): ?>
                                                <?php
                                                $student_count = $classroom->getStudentCount($class['id']);
                                                require_once 'classes/PointSystem.php';
                                                $pointSystem = new PointSystem($db);
                                                $total_points = $pointSystem->getTotalPointsByClass($class['id']);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($class['nom']); ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary-subtle text-primary-emphasis"><?php echo $student_count; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success-subtle text-success-emphasis"><?php echo number_format($total_points); ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($class['created_at'])); ?></small>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="btn-group" role="group">
                                                            <a href="index.php?page=class_view&id=<?php echo $class['id']; ?>" 
                                                               class="btn btn-sm btn-primary">
                                                                Voir
                                                            </a>
                                                            <button class="btn btn-sm btn-outline-secondary edit-class-btn" 
                                                                    data-class-id="<?php echo $class['id']; ?>"
                                                                    data-class-name="<?php echo htmlspecialchars($class['nom']); ?>">
                                                                Modifier
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger delete-class-btn" 
                                                                    data-class-id="<?php echo $class['id']; ?>"
                                                                    data-class-name="<?php echo htmlspecialchars($class['nom']); ?>">
                                                                Supprimer
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Create Class Modal -->
    <div class="modal fade" id="createClassModal" tabindex="-1" aria-labelledby="createClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="createClassModalLabel">Créer une nouvelle classe</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la classe</label>
                            <input type="text" name="nom" id="nom" class="form-control" 
                                   placeholder="Ex: 6ème A, CM2 B..." required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="create_class" class="btn btn-primary">Créer la classe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Class Modal -->
    <div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="editClassModalLabel">Modifier la classe</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="class_id" id="edit_class_id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom de la classe</label>
                            <input type="text" name="nom" id="edit_nom" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="update_class" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="deleteClassModalLabel">Confirmer la suppression</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="class_id" id="delete_class_id">
                        <p>Êtes-vous sûr de vouloir supprimer la classe <strong id="delete_class_name"></strong> ?</p>
                        <div class="alert alert-warning">
                            <strong>Attention:</strong> Cette action supprimera également tous les élèves et leurs points associés à cette classe.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="delete_class" class="btn btn-danger">Supprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Handle edit class button clicks
        document.querySelectorAll('.edit-class-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const classId = btn.dataset.classId;
                const className = btn.dataset.className;
                
                document.getElementById('edit_class_id').value = classId;
                document.getElementById('edit_nom').value = className;
                
                const modal = new bootstrap.Modal(document.getElementById('editClassModal'));
                modal.show();
            });
        });
        
        // Handle delete class button clicks
        document.querySelectorAll('.delete-class-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const classId = btn.dataset.classId;
                const className = btn.dataset.className;
                
                document.getElementById('delete_class_id').value = classId;
                document.getElementById('delete_class_name').textContent = className;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteClassModal'));
                modal.show();
            });
        });
    </script>
</body>
</html>