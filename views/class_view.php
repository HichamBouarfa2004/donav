<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';
require_once 'classes/PointSystem.php';
require_once 'classes/ClassActivity.php';
require_once 'classes/CertificateGenerator.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);
$pointSystem = new PointSystem($db);
$classActivity = new ClassActivity($db);

$class_id = $_GET['id'] ?? 0;

// Handle success message from URL
$message = '';
$error = '';
if (isset($_GET['success'])) {
    $message = urldecode($_GET['success']);
}

// Verify class belongs to teacher
$fetch_result = $classroom->getById($class_id);

// Handle point addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_points'])) {
    if (!$fetch_result  || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        header('Location: index.php?page=dashboard');
        exit;
    }

    // Check if activities exist before allowing points addition
    $activities_check_stmt = $classActivity->getByClass($class_id);
    $existing_activities = $activities_check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($existing_activities)) {
        $error = "Vous devez créer au moins une activité avant de pouvoir ajouter des points.";
    } else {
        $student_id = $_POST['student_id'] ?? 0;
        $points = intval($_POST['points'] ?? 5);
        $selected_activity_id = intval($_POST['selected_activity_id'] ?? 0) ?: null;
    
    // reason can come from reason_select or custom reason
    $reason = 'Participation';
    if (!empty($_POST['reason']) ) {
        $reason = $_POST['reason'];
    } elseif (!empty($_POST['reason_select'])) {
        $rs = $_POST['reason_select'];
        if ($rs === 'Other' && !empty($_POST['reason'])) {
            $reason = $_POST['reason'];
        } else {
            $reason = $rs;
        }
    }
    
    if ($student->getById($student_id)) {
        if ($student->addPoints($points, $reason, $selected_activity_id)) {
            // Create success message
            $success_msg = urlencode("Points ajoutés avec succès à " . $student->nom);
            
            // Check if student reached 100 points for certificate
            if ($student->points >= 100) {
                $success_msg = urlencode("Points ajoutés avec succès à " . $student->nom . " - Certificat disponible!");
            }
            
            // Redirect with success message and preserve activity
            $redirect_url = "?page=class_view&id={$class_id}&success=" . $success_msg;
            if ($selected_activity_id) {
                $redirect_url .= "&activity=" . $selected_activity_id;
            }
            header("Location: " . $redirect_url);
            exit;
        } else {
            $error = "Erreur lors de l'ajout des points. Consultez les logs pour plus de détails.";
        }
    } else {
        $error = "Étudiant introuvable.";
    }
} // Close the else block for activities check
} // Close the main POST handler

// Handle creating a class activity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_activity'])) {
    $title = trim($_POST['activity_title'] ?? '');
    if (!empty($title) && $classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        $classActivity->class_id = $class_id;
        $classActivity->title = $title;
        if ($classActivity->create()) {
            header("location: ?page=class_view&id={$class_id}");
            exit;
        } else {
            $error = 'Impossible d\'ajouter l\'activité.';
        }
    }
}

// Handle deleting a class activity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_activity'])) {
    $activity_id = intval($_POST['activity_id'] ?? 0);
    if ($activity_id && $classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        if ($classActivity->delete($activity_id, $class_id)) {
            header("location: ?page=class_view&id={$class_id}");
            exit;
        } else {
            $error = 'Impossible de supprimer l\'activité.';
        }
    }
}

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

// handle Certificate generation (automatic based on current activity)
if (isset($_GET['generate_cert']) && isset($_GET['student_id']) && isset($_GET['activity_id'])) {
    $student_id = intval($_GET['student_id']);
    $activity_id = intval($_GET['activity_id']);
    
    // Verify class belongs to teacher
    if (!$classroom->getById($class_id) || $classroom->enseignant_id != $_SESSION['teacher_id']) {
        header('Location: index.php?page=dashboard');
        exit;
    }

    if ($student->getById($student_id)) {
        // Get activity-specific points for this student
        $activity_points = $student->getPointsForActivity($activity_id);
        
        if ($activity_points >= 100) {
            // Get activity title
            $activity_data = $classActivity->getById($activity_id);
            $activity_title = $activity_data ? $activity_data['title'] : 'Class Activity';
            
            // Get teacher information for certificate
            require_once 'classes/Teacher.php';
            $teacher = new Teacher($db);
            $teacher->getById($_SESSION['teacher_id']);
            
            $certificate_generator = new CertificateGenerator(
                $student->nom, 
                $classroom->nom, 
                $teacher->nom, 
                $activity_title
            );

            $certificate_generator->generateCertificate($student->nom . "_" . $activity_title . "_Certificate.pdf");

            // Redirect back with success message and preserve activity
            $success_msg = urlencode("Certificat généré pour " . $student->nom . " - " . $activity_title);
            header("location: ?page=class_view&id={$class_id}&activity={$activity_id}&success=" . $success_msg);
            exit;
        }
    }
}

// Get students in this class
$students_stmt = $student->getByClass($class_id);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity for this class
$activity_stmt = $pointSystem->getByClass($class_id, 20);
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Load class activities (custom per-class list)
$class_activities_stmt = $classActivity->getByClass($class_id);
$class_activities = $class_activities_stmt->fetchAll(PDO::FETCH_ASSOC);
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
<body class="bg-light">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 250px; padding-top: 70px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm position-fixed" style="left: 250px; right: 0; top: 0; z-index: 1020; height: 70px;">
            <div class="container-fluid px-4">
                <button class="btn btn-outline-secondary d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas">
                    <i class="bi bi-list"></i>
                </button>

                <h1 class="navbar-brand mb-0 h1">Classe : <?php echo htmlspecialchars($classroom->nom); ?></h1>

                <div class="d-flex align-items-center">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid py-4">
            <?php if (isset($message) && !empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row g-3 align-items-center mb-4">
                <div class="col-12 col-xl-8">
                    <h1 class="h3 mb-1">Classe : <?php echo htmlspecialchars($classroom->nom); ?></h1>
                    <p class="text-muted mb-0"><?php echo count($students); ?> élèves inscrits</p>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-xl-end">
                        <a href="index.php?page=add_student&class_id=<?php echo $class_id; ?>" class="btn btn-outline-secondary">
                            Ajouter un élève
                        </a>
                        <a href="index.php?page=import_students&class_id=<?php echo $class_id; ?>" class="btn btn-primary">
                            Importer Excel
                        </a>
                        <?php if (!empty($students)): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAllStudentsModal">
                                Supprimer tous
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Filter -->
            <?php if (!empty($class_activities)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <h5 class="mb-2 mb-md-0">Filtrer par activité</h5>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <select id="activityFilter" class="form-select">
                                            <?php foreach ($class_activities as $ca): ?>
                                                <option value="<?php echo $ca['id']; ?>"><?php echo htmlspecialchars($ca['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-info border-0 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle me-3 fs-4"></i>
                                <div>
                                    <h6 class="alert-heading mb-1">Aucune activité créée</h6>
                                    <p class="mb-0">Vous devez créer au moins une activité pour pouvoir ajouter des points aux élèves. Utilisez la section "Activités de la classe" ci-dessous pour commencer.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>



            <div class="row g-4">
                <div class="col-12 col-xl-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white border-0 pb-0">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div>
                                    <h2 class="h5 mb-1">Élèves</h2>
                                    <p class="text-muted mb-0">Suivez la progression et attribuez des points instantanément.</p>
                                </div>
                                <div class="w-100" style="max-width: 260px;">
                                    <input type="text"
                                           class="form-control table-search"
                                           data-table="studentsTable"
                                           placeholder="Rechercher un élève...">
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($students)): ?>
                                <div class="text-center py-5">
                                    <p class="text-muted mb-3">Aucun élève dans cette classe pour le moment.</p>
                                    <a href="index.php?page=add_student&class_id=<?php echo $class_id; ?>" class="btn btn-primary">
                                        Ajouter le premier élève
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle table-hover mb-0" id="studentsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Élève</th>
                                                <th scope="col" class="text-center">Points</th>
                                                <th scope="col">Progression</th>
                                                <th scope="col" class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="studentsTableBody">
                                            <?php foreach ($students as $student_data): ?>
                                                <?php
                                                    // Get activity breakdown for this student
                                                    $temp_student = new Student($db);
                                                    $temp_student->getById($student_data['id']);
                                                    $activity_breakdown = $temp_student->getActivityBreakdown();
                                                    
                                                    $isCertificateReady = $student_data['points'] >= 100;
                                                    $progress = min(($student_data['points'] / 100) * 100, 100);
                                                    $progressClass = 'bg-info';

                                                    if ($student_data['points'] >= 80) {
                                                        $progressClass = 'bg-success';
                                                    } elseif ($student_data['points'] >= 50) {
                                                        $progressClass = 'bg-warning';
                                                    }
                                                ?>
                                                <tr class="student-row" 
                                                    data-student-id="<?php echo $student_data['id']; ?>"
                                                    data-total-points="<?php echo $student_data['points']; ?>"
                                                    data-activities='<?php echo json_encode($activity_breakdown, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student_data['nom']); ?></span>
                                                            <?php if ($isCertificateReady): ?>
                                                                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle mt-2">Certificat prêt</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2 points-display">
                                                            <?php echo $student_data['points']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-column gap-2">
                                                            <div class="progress" style="height: 6px;">
                                                                <div class="progress-bar progress-bar-display <?php echo $progressClass; ?>" role="progressbar"
                                                                     style="width: <?php echo $progress; ?>%;"
                                                                     aria-valuenow="<?php echo $progress; ?>"
                                                                     aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <small class="text-muted points-text"><?php echo $student_data['points']; ?>/100 points</small>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="action-buttons">
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-success certificate-btn"
                                                                    onclick="generateCertificateForCurrentActivity(<?php echo $student_data['id']; ?>)"
                                                                    style="<?php echo $isCertificateReady ? '' : 'display: none;'; ?>">
                                                                Certificat
                                                            </button>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-primary add-points-btn"
                                                                    data-student-id="<?php echo $student_data['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student_data['nom']); ?>"
                                                                    onclick="openPointsModal(<?php echo $student_data['id']; ?>, '<?php echo htmlspecialchars($student_data['nom'], ENT_QUOTES); ?>');"
                                                                    style="<?php echo ($isCertificateReady || empty($class_activities)) ? 'display: none;' : ''; ?>">
                                                                <i class="bi bi-plus-circle me-1"></i>Ajouter des points
                                                            </button>
                                                            <?php if (empty($class_activities) && !$isCertificateReady): ?>
                                                                <span class="text-muted small">
                                                                    <i class="bi bi-info-circle me-1"></i>Créez une activité pour ajouter des points
                                                                </span>
                                                            <?php endif; ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-danger delete-student-btn"
                                                                    data-student-id="<?php echo $student_data['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student_data['nom']); ?>"
                                                                    onclick="openDeleteStudentModal(<?php echo $student_data['id']; ?>, '<?php echo htmlspecialchars($student_data['nom'], ENT_QUOTES); ?>');">
                                                                <i class="bi bi-trash me-1"></i>Supprimer
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

                <div class="col-12 col-xl-4">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h5 mb-1">Activités de la classe</h2>
                                <p class="text-muted mb-0">Gérez les raisons prédéfinies pour cette classe.</p>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="showAddActivityBtn">Ajouter</button>
                        </div>
                        <div class="card-body">
                            <div id="classActivitiesList">
                                <?php if (empty($class_activities)): ?>
                                    <p class="text-muted text-center mb-0">Aucune activité définie pour cette classe.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($class_activities as $ca): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($ca['title']); ?>
                                                <form method="POST" class="d-inline" style="margin:0;">
                                                    <input type="hidden" name="delete_activity" value="1">
                                                    <input type="hidden" name="activity_id" value="<?php echo $ca['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            <div id="addActivityForm" style="display:none; margin-top:12px;">
                                <form method="POST">
                                    <input type="hidden" name="create_activity" value="1">
                                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                                    <div class="input-group">
                                        <input type="text" name="activity_title" class="form-control" placeholder="Titre de l'activité">
                                        <button class="btn btn-primary" type="submit">Ajouter</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white border-0 pb-0">
                            <h2 class="h5 mb-1">Activité récente</h2>
                            <p class="text-muted mb-0">Derniers ajouts de points dans cette classe.</p>
                        </div>
                        <div class="card-body">
                            <?php if (empty($activities)): ?>
                                <p class="text-muted text-center mb-0">Aucune activité récente.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <h4 class="h6 mb-1"><?php echo htmlspecialchars($activity['eleve_nom']); ?></h4>
                                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($activity['raison']); ?></p>
                                                    <small class="text-body-secondary"><?php echo date('d/m/Y H:i', strtotime($activity['date_ajout'])); ?></small>
                                                </div>
                                                <div class="text-nowrap">
                                                    <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">+<?php echo $activity['points_ajoutes']; ?> pts</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="addPointsModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="addPointsModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="addPointsModalLabel">Ajouter des points</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer" data-modal-close></button>
                </div>
                <form method="POST" action="?page=class_view&id=<?php echo $class_id; ?>">
                    <div class="modal-body">
                        <p class="mb-4">Attribuer des points à <strong class="student-name"></strong>.</p>

                        <input type="hidden" name="student_id" value="">
                        <input type="hidden" name="add_points" value="1">
                        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                        <input type="hidden" name="selected_activity_id" id="selectedActivityId" value="">

                        <div class="mb-3">
                            <label for="points" class="form-label">Nombre de points</label>
                            <select name="points" id="points" class="form-select">
                                <option value="5">5 points (Participation)</option>
                                <option value="10">10 points (Bon travail)</option>
                                <option value="15">15 points (Excellent travail)</option>
                                <option value="20">20 points (Exceptionnel)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="reason_select" class="form-label">Raison</label>
                            <select name="reason_select" id="reason_select" class="form-select">
                                <?php if (!empty($class_activities)): ?>
                                    <?php foreach ($class_activities as $ca): ?>
                                        <option value="<?php echo htmlspecialchars($ca['title'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($ca['title']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="Other">Autre...</option>
                                <?php else: ?>
                                    <option value="" disabled selected>Aucune activité disponible</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="customReasonWrapper" style="display:none;">
                            <label for="reason" class="form-label">Raison personnalisée</label>
                            <input type="text" name="reason" id="reason" class="form-control" placeholder="Saisir la raison">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-modal-close>Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter les points</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete All Students Modal -->
    <div id="deleteAllStudentsModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="deleteAllStudentsModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="deleteAllStudentsModalLabel">Supprimer tous les élèves</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="?page=class_view&id=<?php echo $class_id; ?>">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Attention !</strong> Cette action est irréversible.
                        </div>
                        <p class="mb-3">Êtes-vous sûr de vouloir supprimer <strong>tous les élèves</strong> de la classe <strong><?php echo htmlspecialchars($classroom->nom); ?></strong> ?</p>
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Cette action supprimera également tout l'historique des points de ces élèves.
                        </p>
                        <input type="hidden" name="delete_all_students" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Supprimer tous les élèves
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Individual Student Modal -->
    <div id="deleteStudentModal" class="modal fade" tabindex="-1" aria-hidden="true" aria-labelledby="deleteStudentModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="deleteStudentModalLabel">Supprimer l'élève</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" action="?page=class_view&id=<?php echo $class_id; ?>">
                    <div class="modal-body">
                        <p class="mb-3">Êtes-vous sûr de vouloir supprimer l'élève <strong id="deleteStudentName"></strong> ?</p>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Cette action supprimera également tout l'historique des points de cet élève.
                        </div>
                        <input type="hidden" name="delete_student" value="1">
                        <input type="hidden" name="student_id" id="deleteStudentId" value="">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Supprimer l'élève
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
        // Function to open points modal
        function openPointsModal(studentId, studentName) {
            // Check if activities exist
            const activityFilter = document.getElementById('activityFilter');
            if (!activityFilter) {
                alert('Vous devez créer au moins une activité avant de pouvoir ajouter des points.');
                return;
            }
            
            const modal = document.getElementById('addPointsModal');
            if (!modal) return;
            
            // Update modal content
            const studentNameSpan = modal.querySelector('.student-name');
            const studentIdInput = modal.querySelector('input[name="student_id"]');
            const selectedActivityInput = modal.querySelector('#selectedActivityId');
            
            if (studentNameSpan) {
                studentNameSpan.textContent = studentName;
            }
            
            if (studentIdInput) {
                studentIdInput.value = studentId;
            }
            
            // Set the current selected activity
            if (selectedActivityInput) {
                const activityFilter = document.getElementById('activityFilter');
                if (activityFilter) {
                    selectedActivityInput.value = activityFilter.value;
                }
            }
            
            // Show modal
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        // Function to generate certificate for current activity
        function generateCertificateForCurrentActivity(studentId) {
            const activityFilter = document.getElementById('activityFilter');
            if (!activityFilter) return;
            
            const currentActivityId = activityFilter.value;
            const classId = <?php echo $class_id; ?>;
            
            // Navigate to certificate generation URL
            window.location.href = `?page=class_view&id=${classId}&generate_cert=1&student_id=${studentId}&activity_id=${currentActivityId}`;
        }

        // Function to open delete student modal
        function openDeleteStudentModal(studentId, studentName) {
            const modal = document.getElementById('deleteStudentModal');
            if (!modal) return;
            
            // Update modal content
            const studentNameSpan = document.getElementById('deleteStudentName');
            const studentIdInput = document.getElementById('deleteStudentId');
            
            if (studentNameSpan) {
                studentNameSpan.textContent = studentName;
            }
            
            if (studentIdInput) {
                studentIdInput.value = studentId;
            }
            
            // Show modal
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }

        // Initialize app when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof No9atiApp !== 'undefined') {
                window.no9atiApp = new No9atiApp();
            }
            
            // Activity filter functionality
            const activityFilter = document.getElementById('activityFilter');
            if (activityFilter) {
                // Get preserved activity from URL or localStorage
                const urlParams = new URLSearchParams(window.location.search);
                const selectedFromUrl = urlParams.get('activity');
                const selectedFromStorage = localStorage.getItem('selectedActivity_<?php echo $class_id; ?>');
                const preservedActivity = selectedFromUrl || selectedFromStorage;
                
                // Set the preserved activity if it exists and is valid
                if (preservedActivity && activityFilter.querySelector(`option[value="${preservedActivity}"]`)) {
                    activityFilter.value = preservedActivity;
                }
                
                activityFilter.addEventListener('change', function() {
                    const selectedActivity = this.value;
                    // Store selected activity in localStorage
                    localStorage.setItem('selectedActivity_<?php echo $class_id; ?>', selectedActivity);
                    
                    // Update URL without reloading page
                    const newUrl = new URL(window.location);
                    newUrl.searchParams.set('activity', selectedActivity);
                    window.history.replaceState({}, '', newUrl);
                    
                    updateStudentPointsDisplay(selectedActivity);
                });
                
                // Initialize with preserved or first activity
                if (activityFilter.options.length > 0) {
                    const initialActivity = activityFilter.value;
                    localStorage.setItem('selectedActivity_<?php echo $class_id; ?>', initialActivity);
                    updateStudentPointsDisplay(initialActivity);
                }
            } else {
                // No activities exist - disable points functionality
                console.log('No activities found - points functionality disabled');
            }

            // Toggle custom reason input
            const reasonSelect = document.getElementById('reason_select');
            const customWrapper = document.getElementById('customReasonWrapper');
            if (reasonSelect && customWrapper) {
                reasonSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        customWrapper.style.display = 'block';
                    } else {
                        customWrapper.style.display = 'none';
                    }
                });
            }



            // Show add activity form
            const showAddBtn = document.getElementById('showAddActivityBtn');
            const addForm = document.getElementById('addActivityForm');
            if (showAddBtn && addForm) {
                showAddBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    addForm.style.display = addForm.style.display === 'none' ? 'block' : 'none';
                });
            }
        });

        // Function to update student points display based on selected activity
        function updateStudentPointsDisplay(selectedActivity) {
            const studentRows = document.querySelectorAll('.student-row');
            
            // Update the hidden field for add points modal
            const selectedActivityId = document.getElementById('selectedActivityId');
            if (selectedActivityId) {
                selectedActivityId.value = selectedActivity;
            }
            
            studentRows.forEach(function(row) {
                const activities = JSON.parse(row.dataset.activities || '[]');
                
                let displayPoints = 0;
                
                // Find specific activity
                const specificActivity = activities.find(a => a.id == selectedActivity);
                displayPoints = specificActivity ? parseInt(specificActivity.points) : 0;
                
                // Update points display
                const pointsDisplay = row.querySelector('.points-display');
                const pointsText = row.querySelector('.points-text');
                const progressBar = row.querySelector('.progress-bar-display');
                
                if (pointsDisplay) {
                    pointsDisplay.textContent = displayPoints;
                }
                
                if (pointsText) {
                    pointsText.textContent = displayPoints + '/100 points';
                }
                
                if (progressBar) {
                    const progress = Math.min((displayPoints / 100) * 100, 100);
                    progressBar.style.width = progress + '%';
                    progressBar.setAttribute('aria-valuenow', progress);
                    
                    // Update progress bar color
                    progressBar.classList.remove('bg-info', 'bg-warning', 'bg-success');
                    if (displayPoints >= 80) {
                        progressBar.classList.add('bg-success');
                    } else if (displayPoints >= 50) {
                        progressBar.classList.add('bg-warning');
                    } else {
                        progressBar.classList.add('bg-info');
                    }
                }
                
                // Update certificate button visibility based on current activity points
                const certificateBtn = row.querySelector('.certificate-btn');
                const addPointsBtn = row.querySelector('.add-points-btn');
                const isCertificateReady = displayPoints >= 100;
                
                if (certificateBtn && addPointsBtn) {
                    if (isCertificateReady) {
                        certificateBtn.style.display = 'inline-block';
                        addPointsBtn.style.display = 'none';
                    } else {
                        certificateBtn.style.display = 'none';
                        addPointsBtn.style.display = 'inline-block';
                    }
                }
                
                // Update certificate ready badge
                const certificateBadge = row.querySelector('.badge.bg-success-subtle');
                if (certificateBadge) {
                    if (isCertificateReady) {
                        // Show certificate ready badge for specific activities with 100+ points
                        certificateBadge.style.display = 'inline-block';
                    } else {
                        certificateBadge.style.display = 'none';
                    }
                }
            });
        }
    </script>
</body>
</html>