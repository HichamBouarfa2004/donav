<?php
if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Student.php';
require_once 'classes/PointSystem.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$student = new Student($db);
$pointSystem = new PointSystem($db);

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_classes = count($classes);
$total_students = 0;
$total_points = 0;
$students_ready_for_certificate = 0;

foreach ($classes as $class) {
    $student_count = $classroom->getStudentCount($class['id']);
    $total_students += $student_count;
    
    $class_total_points = $pointSystem->getTotalPointsByClass($class['id']);
    $total_points += $class_total_points;
    
    // Count students ready for certificate (100+ points)
    $students_stmt = $student->getByClass($class['id']);
    while ($student_data = $students_stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($student_data['points'] >= 100) {
            $students_ready_for_certificate++;
        }
    }
}

// Get recent activity
$recent_activity = $pointSystem->getRecentActivity($_SESSION['teacher_id'], 7);
$activities = $recent_activity->fetchAll(PDO::FETCH_ASSOC);

// Get top students
$top_students_stmt = $student->getTopStudents($_SESSION['teacher_id'], 5);
$top_students = $top_students_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - No9ati</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-real-time-updates="true" style="background: #f9f9fa;">
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="margin-left: 212px; padding-top: 68px; min-height: 100vh;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.1);">
            <div class="container-fluid px-4">
                <h1 style="font-size: 16px; font-weight: 600; color: #1c1c1c; margin: 0;">Tableau de bord</h1>
                <div class="d-flex align-items-center">
                    <div class="d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 14px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>
        
        <main class="container-fluid" style="padding: 28px;">
            <!-- Page Title Section -->
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div>
                        <h1 style="font-size: 24px; font-weight: 600; color: #1c1c1c; margin-bottom: 4px;">Bonjour, <?php echo htmlspecialchars($_SESSION['teacher_name'] ?? 'Utilisateur'); ?></h1>
                    </div>
                    <div class="d-flex align-items-center gap-2" style="font-size: 14px; color: rgba(0,0,0,0.6);">

                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards (SnowUI Style) -->
            <div class="row g-4 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card stat-card-purple h-100" style="border-radius: 20px; border: none;">
                        <div class="card-body" style="padding: 24px;">
                            <div style="font-size: 14px; color: #1c1c1c; margin-bottom: 8px;">Classes</div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="font-size: 24px; font-weight: 600; color: #1c1c1c;" data-stat="classes"><?php echo $total_classes; ?></div>
                                <div class="d-flex align-items-center gap-1" style="font-size: 14px; color: #22c55e;">
                                    <span>Total</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="7" y1="17" x2="17" y2="7"></line>
                                        <polyline points="7 7 17 7 17 17"></polyline>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-lg-3">
                    <div class="card stat-card-blue h-100" style="border-radius: 20px; border: none;">
                        <div class="card-body" style="padding: 24px;">
                            <div style="font-size: 14px; color: #1c1c1c; margin-bottom: 8px;">Élèves</div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="font-size: 24px; font-weight: 600; color: #1c1c1c;" data-stat="students"><?php echo $total_students; ?></div>
                                <div class="d-flex align-items-center gap-1" style="font-size: 14px; color: #22c55e;">
                                    <span>Total</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="7" y1="17" x2="17" y2="7"></line>
                                        <polyline points="7 7 17 7 17 17"></polyline>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-lg-3">
                    <div class="card stat-card-green h-100" style="border-radius: 20px; border: none;">
                        <div class="card-body" style="padding: 24px;">
                            <div style="font-size: 14px; color: #1c1c1c; margin-bottom: 8px;">Points attribués</div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="font-size: 24px; font-weight: 600; color: #1c1c1c;" data-stat="points"><?php echo number_format($total_points); ?></div>
                                <div class="d-flex align-items-center gap-1" style="font-size: 14px; color: #22c55e;">
                                    <span>+15%</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="7" y1="17" x2="17" y2="7"></line>
                                        <polyline points="7 7 17 7 17 17"></polyline>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-lg-3">
                    <div class="card stat-card-orange h-100" style="border-radius: 20px; border: none;">
                        <div class="card-body" style="padding: 24px;">
                            <div style="font-size: 14px; color: #1c1c1c; margin-bottom: 8px;">Certificats prêts</div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div style="font-size: 24px; font-weight: 600; color: #1c1c1c;" data-stat="certificates"><?php echo $students_ready_for_certificate; ?></div>
                                <div class="d-flex align-items-center gap-1" style="font-size: 14px; color: #22c55e;">
                                    <span>+6%</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="7" y1="17" x2="17" y2="7"></line>
                                        <polyline points="7 7 17 7 17 17"></polyline>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Classes Overview -->
                <div class="col-12 col-xl-8">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Mes Classes</h2>
                            <a href="index.php?page=manage_classes" class="btn btn-sm" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                Gérer les classes
                            </a>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($classes)): ?>
                                <div class="text-center py-5">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.2)" stroke-width="2" style="margin-bottom: 16px;">
                                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <p style="color: rgba(0,0,0,0.4); margin-bottom: 16px;">Vous n'avez pas encore de classes.</p>
                                    <a href="index.php?page=manage_classes" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 12px; padding: 8px 16px; font-size: 14px;">
                                        Créer une classe
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Classe</th>
                                                <th style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Élèves</th>
                                                <th style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Points totaux</th>
                                                <th class="text-end" style="font-size: 12px; font-weight: 500; color: rgba(0,0,0,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 16px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classes as $class): ?>
                                                <?php
                                                $student_count = $classroom->getStudentCount($class['id']);
                                                $class_points = $pointSystem->getTotalPointsByClass($class['id']);
                                                ?>
                                                <tr>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1); font-size: 14px; color: #1c1c1c;">
                                                        <strong><?php echo htmlspecialchars($class['nom']); ?></strong>
                                                    </td>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="background: #edeefc; color: #6366f1; font-size: 12px; font-weight: 500; padding: 4px 8px; border-radius: 8px;"><?php echo $student_count; ?></span>
                                                    </td>
                                                    <td style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 12px; font-weight: 500; padding: 4px 8px; border-radius: 8px;"><?php echo number_format($class_points); ?></span>
                                                    </td>
                                                    <td class="text-end" style="padding: 16px; border-bottom: 1px solid rgba(0,0,0,0.1);">
                                                        <a href="index.php?page=class_view&id=<?php echo $class['id']; ?>" 
                                                           class="btn btn-sm" style="background: #1c1c1c; color: #fff; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                                            Voir
                                                        </a>
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
                
                <!-- Top Students -->
                <div class="col-12 col-xl-4">
                    <div class="card h-100" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Top Élèves</h2>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($top_students)): ?>
                                <p class="text-center" style="color: rgba(0,0,0,0.4);">Aucun élève pour le moment.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($top_students as $index => $student_data): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center" style="border: none; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 16px 0; background: transparent;">
                                            <div>
                                                <h6 style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 4px;"><?php echo htmlspecialchars($student_data['nom']); ?></h6>
                                                <small style="font-size: 12px; color: rgba(0,0,0,0.4);"><?php echo htmlspecialchars($student_data['classe_nom']); ?></small>
                                            </div>
                                            <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 8px;">
                                                <?php echo $student_data['points']; ?> pts
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card" style="background: #fff; border-radius: 20px; border: none;">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: transparent; border: none; padding: 24px; padding-bottom: 16px;">
                            <h2 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">Activité récente (7 derniers jours)</h2>
                            <a href="index.php?page=points_log" class="btn btn-sm" style="background: rgba(0,0,0,0.04); color: #1c1c1c; border: none; border-radius: 8px; font-size: 12px; padding: 4px 12px;">
                                Voir tout l'historique
                            </a>
                        </div>
                        <div class="card-body" style="padding: 24px; padding-top: 0;">
                            <?php if (empty($activities)): ?>
                                <p class="text-center" style="color: rgba(0,0,0,0.4);">Aucune activité récente.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="list-group-item" style="border: none; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 16px 0; background: transparent;">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin-bottom: 4px;"><?php echo htmlspecialchars($activity['eleve_nom']); ?></h6>
                                                    <p style="font-size: 14px; color: rgba(0,0,0,0.6); margin-bottom: 4px;"><?php echo htmlspecialchars($activity['raison']); ?> - 
                                                       <?php echo htmlspecialchars($activity['classe_nom']); ?></p>
                                                    <small style="font-size: 12px; color: rgba(0,0,0,0.4);"><?php echo date('d/m/Y H:i', strtotime($activity['date_ajout'])); ?></small>
                                                </div>
                                                <span style="background: rgba(34, 197, 94, 0.1); color: #22c55e; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 8px;">
                                                    +<?php echo $activity['points_ajoutes']; ?> pts
                                                </span>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa.js"></script>
    <script>
        // Initialize No9ati App
        document.addEventListener('DOMContentLoaded', () => {
            window.no9atiApp = new No9atiApp();
        });
    </script>
</body>
</html>