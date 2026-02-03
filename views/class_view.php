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

// Handle absence recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_absence'])) {
    // Removed - absences now managed from dedicated page
}

// Handle absence deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_absence'])) {
    // Removed - absences now managed from dedicated page
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
<body style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
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

            <!-- Activity Filter -->
            <?php if (!empty($class_activities)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                            <div style="padding: 20px 24px;">
                                <div class="row align-items-center">
                                    <div class="col-12 col-md-6">
                                        <h5 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 8px; margin-bottom: 0;">Filtrer par activité</h5>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <select id="activityFilter" class="form-select" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 10px 16px; font-size: 14px;">
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
                        <div class="d-flex align-items-center" style="background: #e8f4fd; border-radius: 12px; padding: 16px 20px;">
                            <svg class="me-3" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <div>
                                <h6 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;">Aucune activité créée</h6>
                                <p style="font-size: 14px; color: rgba(0,0,0,0.6); margin: 0;">Vous devez créer au moins une activité pour pouvoir ajouter des points aux élèves. Utilisez la section "Activités de la classe" ci-dessous pour commencer.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>



            <div class="row g-4">
                <div class="col-12 col-xl-8">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div>
                                    <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;">Élèves</h2>
                                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">Suivez la progression et attribuez des points instantanément.</p>
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
                                                <th scope="col" class="text-center" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Points</th>
                                                <th scope="col" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Progression</th>
                                                <th scope="col" class="text-end" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Actions</th>
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
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <div class="d-flex flex-column">
                                                            <span style="font-size: 14px; font-weight: 500; color: #1c1c1c;"><?php echo htmlspecialchars($student_data['nom']); ?></span>
                                                            <?php if ($isCertificateReady): ?>
                                                                <span style="display: inline-block; background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 6px; margin-top: 6px; width: fit-content;">Certificat prêt</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span class="points-display" style="background: #edeefc; color: #6366f1; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 8px;">
                                                            <?php echo $student_data['points']; ?>
                                                        </span>
                                                    </td>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <div class="d-flex flex-column gap-2">
                                                            <div class="progress" style="height: 6px; background: rgba(0,0,0,0.05); border-radius: 3px;">
                                                                <div class="progress-bar progress-bar-display <?php echo $progressClass; ?>" role="progressbar"
                                                                     style="width: <?php echo $progress; ?>%; border-radius: 3px;"
                                                                     aria-valuenow="<?php echo $progress; ?>"
                                                                     aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <small class="points-text" style="font-size: 12px; color: rgba(0,0,0,0.4);"><?php echo $student_data['points']; ?>/100 points</small>
                                                        </div>
                                                    </td>
                                                    <td class="text-end" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <div class="action-buttons d-flex gap-2 justify-content-end flex-wrap">
                                                            <button type="button" 
                                                                    class="btn btn-sm certificate-btn"
                                                                    onclick="generateCertificateForCurrentActivity(<?php echo $student_data['id']; ?>)"
                                                                    style="background: rgba(34, 197, 94, 0.1); color: #22c55e; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px; <?php echo $isCertificateReady ? '' : 'display: none;'; ?>">
                                                                Certificat
                                                            </button>
                                                            <button type="button" 
                                                                    class="btn btn-sm add-points-btn"
                                                                    data-student-id="<?php echo $student_data['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($student_data['nom']); ?>"
                                                                    onclick="openPointsModal(<?php echo $student_data['id']; ?>, '<?php echo htmlspecialchars($student_data['nom'], ENT_QUOTES); ?>');"
                                                                    style="background: #1c1c1c; color: #fff; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px; <?php echo ($isCertificateReady || empty($class_activities)) ? 'display: none;' : ''; ?>">
                                                                <svg class="me-1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>Ajouter des points
                                                            </button>
                                                            <?php if (empty($class_activities) && !$isCertificateReady): ?>
                                                                <span style="font-size: 12px; color: rgba(0,0,0,0.4);">
                                                                    <svg class="me-1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>Créez une activité
                                                                </span>
                                                            <?php endif; ?>
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

                <div class="col-12 col-xl-4">
                    <div class="card mb-3" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <div>
                                <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;">Activités de la classe</h2>
                                <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">Gérez les raisons prédéfinies pour cette classe.</p>
                            </div>
                            <button class="btn btn-sm" id="showAddActivityBtn" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px; padding: 4px 12px;">Ajouter</button>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <div id="classActivitiesList">
                                <?php if (empty($class_activities)): ?>
                                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); text-align: center; margin: 0;">Aucune activité définie pour cette classe.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($class_activities as $ca): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center" style="border: none; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                <span style="font-size: 14px; color: #1c1c1c;"><?php echo htmlspecialchars($ca['title']); ?></span>
                                                <form method="POST" class="d-inline" style="margin:0;">
                                                    <input type="hidden" name="delete_activity" value="1">
                                                    <input type="hidden" name="activity_id" value="<?php echo $ca['id']; ?>">
                                                    <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px;">Supprimer</button>
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
                                        <input type="text" name="activity_title" class="form-control" placeholder="Titre de l'activité" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px 0 0 12px; padding: 10px 16px; font-size: 14px;">
                                        <button class="btn" type="submit" style="background: #1c1c1c; color: #fff; border-radius: 0 12px 12px 0; font-size: 14px; padding: 10px 16px;">Ajouter</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;">Activité récente</h2>
                            <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">Derniers ajouts de points dans cette classe.</p>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($activities)): ?>
                                <p style="font-size: 14px; color: rgba(0,0,0,0.4); text-align: center; margin: 0;">Aucune activité récente.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="list-group-item px-0" style="border: none; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                            <div class="d-flex justify-content-between align-items-start gap-3">
                                                <div>
                                                    <h4 style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 4px;"><?php echo htmlspecialchars($activity['eleve_nom']); ?></h4>
                                                    <p style="font-size: 12px; color: rgba(0,0,0,0.6); margin-bottom: 4px;"><?php echo htmlspecialchars($activity['raison']); ?></p>
                                                    <small style="font-size: 11px; color: rgba(0,0,0,0.4);"><?php echo date('d/m/Y H:i', strtotime($activity['date_ajout'])); ?></small>
                                                </div>
                                                <div class="text-nowrap">
                                                    <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 8px;">+<?php echo $activity['points_ajoutes']; ?> pts</span>
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
            <div class="modal-content" style="border: none; border-radius: 20px;">
                <div class="modal-header" style="border: none; padding: 24px; padding-bottom: 16px;">
                    <h3 class="modal-title" id="addPointsModalLabel" style="font-size: 16px; font-weight: 600; color: #1c1c1c;">Ajouter des points</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer" data-modal-close></button>
                </div>
                <form method="POST" action="?page=class_view&id=<?php echo $class_id; ?>">
                    <div class="modal-body" style="padding: 24px; padding-top: 0;">
                        <p style="font-size: 14px; color: rgba(0,0,0,0.6); margin-bottom: 20px;">Attribuer des points à <strong class="student-name" style="color: #1c1c1c;"></strong>.</p>

                        <input type="hidden" name="student_id" value="">
                        <input type="hidden" name="add_points" value="1">
                        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                        <input type="hidden" name="selected_activity_id" id="selectedActivityId" value="">

                        <div class="mb-3">
                            <label for="points" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Nombre de points</label>
                            <select name="points" id="points" class="form-select" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                                <option value="5">5 points (Participation)</option>
                                <option value="10">10 points (Bon travail)</option>
                                <option value="15">15 points (Excellent travail)</option>
                                <option value="20">20 points (Exceptionnel)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="reason_select" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Raison</label>
                            <select name="reason_select" id="reason_select" class="form-select" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
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
                            <label for="reason" style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 8px; display: block;">Raison personnalisée</label>
                            <input type="text" name="reason" id="reason" class="form-control" placeholder="Saisir la raison" style="border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 12px 16px; font-size: 14px;">
                        </div>
                    </div>
                    <div class="modal-footer" style="border: none; padding: 24px; padding-top: 0; gap: 12px;">
                        <button type="button" class="btn" data-bs-dismiss="modal" data-modal-close style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 12px; padding: 10px 20px; font-size: 14px;">Annuler</button>
                        <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 10px 20px; font-size: 14px;">Ajouter les points</button>
                    </div>
                </form>
            </div>
        </div>
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
                            Cette action supprimera également tout l'historique des points de ces élèves.
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
                                <span style="font-size: 14px;">Cette action supprimera également tout l'historique des points de cet élève.</span>
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