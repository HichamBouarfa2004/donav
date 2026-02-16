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

$class_id = $_GET['id'] ?? 0;

// Handle success message from URL
$message = '';
$error = '';
if (isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
}

// Verify class belongs to teacher
$fetch_result = $classroom->getById($class_id);

// Handle deleting all students
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_all_students'])) {
    // Verify class belongs to teacher
    if ($classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        if ($student->deleteAllByClass($class_id)) {
            $message = 'Tous les élèves ont été supprimés avec succès.';
        } else {
            $error = 'Erreur lors de la suppression des élèves.';
        }
    } else {
        $error = 'Action non autorisée.';
    }
}

// Handle deleting individual student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_student'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    
    // Verify class belongs to teacher and student exists
    if ($classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        if ($student->getById($student_id) && $student->classe_id == $class_id) {
            $student_name = $student->nom;
            if ($student->delete()) {
                $message = "L'élève " . htmlspecialchars($student_name) . " a été supprimé avec succès.";
            } else {
                $error = 'Erreur lors de la suppression de l\'élève.';
            }
        } else {
            $error = 'Élève introuvable ou action non autorisée.';
        }
    } else {
        $error = 'Action non autorisée.';
    }
}

// Handle absence recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_absence'])) {
    // Removed - absences now managed from dedicated page
}

// Handle absence deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_absence'])) {
    // Removed - absences now managed from dedicated page
}

// Get students in this class
$students_stmt = $student->getByClass($class_id);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Classe <?php echo htmlspecialchars($classroom->nom); ?> - No9ati</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="padding-top: 88px;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <button class="btn d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Classe : <?php echo htmlspecialchars($classroom->nom); ?></h1>

                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 28px;">
            <?php if (isset($message) && !empty($message)): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-dismissible fade show" role="alert" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 16px;">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-3 align-items-center mb-4">
                <div class="col-12 col-xl-8">
                    <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px;">Classe : <?php echo htmlspecialchars($classroom->nom); ?></h1>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;"><?php echo count($students); ?> élèves inscrits</p>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-xl-end">
                        <a href="index.php?page=teams&class_id=<?php echo $class_id; ?>" class="btn" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border: none; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                            <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            Équipes
                        </a>
                        <a href="index.php?page=add_student&class_id=<?php echo $class_id; ?>" class="btn" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                            <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            Ajouter un élève
                        </a>
                        <a href="index.php?page=import_students&class_id=<?php echo $class_id; ?>" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                            <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            Importer Excel
                        </a>
                        <?php if (!empty($students)): ?>
                            <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#deleteAllStudentsModal" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                <svg class="me-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                Supprimer tous
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>



            <div class="row g-4">
                <div class="col-12">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div>
                                    <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;">Élèves</h2>
                                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">Liste des élèves de cette classe.</p>
                                </div>
                                <div class="w-100" style="max-width: 260px;">
                                    <input type="text"
                                           class="form-control table-search"
                                           data-table="studentsTable"
                                           placeholder="Rechercher un élève..."
                                           style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($students)): ?>
                                <div class="text-center py-5">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <p style="color: rgba(0,0,0,0.4); margin-bottom: 16px;">Aucun élève dans cette classe pour le moment.</p>
                                    <a href="index.php?page=add_student&class_id=<?php echo $class_id; ?>" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                        Ajouter le premier élève
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle table-hover mb-0" id="studentsTable">
                                        <thead>
                                            <tr>
                                                <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Élève</th>
                                                <th scope="col" class="text-end" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="studentsTableBody">
                                            <?php foreach ($students as $student_data): ?>
                                                <tr class="student-row" data-student-id="<?php echo $student_data['id']; ?>">
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="font-size: 14px; font-weight: 500; color: #1c1c1c;"><?php echo htmlspecialchars($student_data['nom']); ?></span>
                                                    </td>
                                                    <td class="text-end" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <div class="action-buttons d-flex gap-2 justify-content-end flex-wrap">
                                                            <button type="button" 
                                                                    class="btn btn-sm delete-student-btn"
                                                                    data-student-id="<?php echo $student_data['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student_data['nom']); ?>"
                                                                    onclick="openDeleteStudentModal(<?php echo $student_data['id']; ?>, '<?php echo htmlspecialchars($student_data['nom'], ENT_QUOTES); ?>');"
                                                                    style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                                                <svg class="me-1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Supprimer
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

    <!-- Delete All Students Modal -->
    <div id="deleteAllStudentsModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="deleteAllStudentsModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 20px;">
                <div class="modal-header" style="border: none; padding: 24px; padding-bottom: 16px;">
                    <h3 class="modal-title" id="deleteAllStudentsModalLabel" style="font-size: 16px; font-weight: 600; color: #1c1c1c;">Supprimer tous les élèves</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="?page=class_view&id=<?php echo $class_id; ?>">
                    <div class="modal-body" style="padding: 24px; padding-top: 0;">
                        <div style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                            <div class="d-flex align-items-center">
                                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                    <line x1="12" y1="9" x2="12" y2="13"></line>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                                <strong>Attention !</strong> Cette action est irréversible.
                            </div>
                        </div>
                        <p style="font-size: 14px; color: #1c1c1c; margin-bottom: 12px;">Êtes-vous sûr de vouloir supprimer <strong>tous les élèves</strong> de la classe <strong><?php echo htmlspecialchars($classroom->nom); ?></strong> ?</p>
                        <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">
                            <svg class="me-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            Cette action supprimera également toutes les données associées à ces élèves.
                        </p>
                        <input type="hidden" name="delete_all_students" value="1">
                    </div>
                    <div class="modal-footer" style="border: none; padding: 24px; padding-top: 0; gap: 12px;">
                        <button type="button" class="btn" data-bs-dismiss="modal" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 10px 20px; font-size: 14px;">Annuler</button>
                        <button type="submit" class="btn" style="background: #ef4444; color: #fff; border: none; border-radius: 12px; padding: 10px 20px; font-size: 14px;">
                            <svg class="me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Supprimer tous les élèves
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Individual Student Modal -->
    <div id="deleteStudentModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="deleteStudentModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border: none; border-radius: 20px;">
                <div class="modal-header" style="border: none; padding: 24px; padding-bottom: 16px;">
                    <h3 class="modal-title" id="deleteStudentModalLabel" style="font-size: 16px; font-weight: 600; color: #1c1c1c;">Supprimer l'élève</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="?page=class_view&id=<?php echo $class_id; ?>">
                    <div class="modal-body" style="padding: 24px; padding-top: 0;">
                        <p style="font-size: 14px; color: #1c1c1c; margin-bottom: 12px;">Êtes-vous sûr de vouloir supprimer l'élève <strong id="deleteStudentName"></strong> ?</p>
                        <div style="background: #fef4e6; color: #f59e0b; border-radius: 12px; padding: 16px;">
                            <div class="d-flex align-items-center">
                                <svg class="me-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                    <line x1="12" y1="9" x2="12" y2="13"></line>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                                <span style="font-size: 14px;">Cette action est irréversible.</span>
                            </div>
                        </div>
                        <input type="hidden" name="delete_student" value="1">
                        <input type="hidden" name="student_id" id="deleteStudentId" value="">
                    </div>
                    <div class="modal-footer" style="border: none; padding: 24px; padding-top: 0; gap: 12px;">
                        <button type="button" class="btn" data-bs-dismiss="modal" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 10px 20px; font-size: 14px;">Annuler</button>
                        <button type="submit" class="btn" style="background: #ef4444; color: #fff; border: none; border-radius: 12px; padding: 10px 20px; font-size: 14px;">
                            <svg class="me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Supprimer l'élève
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa.js"></script>
    <script>
        // Function to open delete student modal
        function openDeleteStudentModal(studentId, studentName) {
            const modal = document.getElementById('deleteStudentModal');
            if (!modal) return;
            
            const studentNameSpan = document.getElementById('deleteStudentName');
            const studentIdInput = document.getElementById('deleteStudentId');
            
            if (studentNameSpan) {
                studentNameSpan.textContent = studentName;
            }
            
            if (studentIdInput) {
                studentIdInput.value = studentId;
            }
            
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        // Initialize app when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof No9atiApp !== 'undefined') {
                window.no9atiApp = new No9atiApp();
            }
        });
    </script>
</body>
</html>