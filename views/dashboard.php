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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'views/partials/sidebar.php'; ?>
    <?php include 'views/partials/header.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">Overview</h2>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-title">Classes</div>
                <div class="stat-card-content">
                    <div class="stat-card-number"><?php echo $total_classes; ?></div>
                    <div class="stat-card-change positive">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 12V4M8 4L4 8M8 4L12 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        +11.01%
                    </div>
                </div>
            </div>
            
            <div class="stat-card stat-card-orange">
                <div class="stat-card-title">Élèves</div>
                <div class="stat-card-content">
                    <div class="stat-card-number"><?php echo $total_students; ?></div>
                    <div class="stat-card-change negative">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 4V12M8 12L4 8M8 12L12 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        -0.03%
                    </div>
                </div>
            </div>
            
            <div class="stat-card stat-card-yellow">
                <div class="stat-card-title">Points attribués</div>
                <div class="stat-card-content">
                    <div class="stat-card-number"><?php echo number_format($total_points); ?></div>
                    <div class="stat-card-change positive">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 12V4M8 4L4 8M8 4L12 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        +15.03%
                    </div>
                </div>
            </div>
            
            <div class="stat-card stat-card-blue">
                <div class="stat-card-title">Certificats prêts</div>
                <div class="stat-card-content">
                    <div class="stat-card-number"><?php echo $students_ready_for_certificate; ?></div>
                    <div class="stat-card-change positive">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 12V4M8 4L4 8M8 4L12 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        +6.08%
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Classes Overview -->
            <div class="content-block content-block-large">
                <div class="content-block-header">
                    <h3 class="content-block-title">Mes Classes</h3>
                    <a href="index.php?page=manage_classes" class="btn-modern btn-modern-primary">
                        Gérer les classes
                    </a>
                </div>
                <div class="content-block-body">
                    <?php if (empty($classes)): ?>
                        <div class="empty-state">
                            <p>Vous n'avez pas encore de classes.</p>
                            <a href="index.php?page=manage_classes" class="btn-modern btn-modern-primary">
                                Créer une classe
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Classe</th>
                                        <th>Élèves</th>
                                        <th>Points totaux</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <?php
                                        $student_count = $classroom->getStudentCount($class['id']);
                                        $class_points = $pointSystem->getTotalPointsByClass($class['id']);
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($class['nom']); ?></strong></td>
                                            <td><span class="badge badge-primary"><?php echo $student_count; ?></span></td>
                                            <td><span class="badge badge-success"><?php echo number_format($class_points); ?></span></td>
                                            <td class="text-end">
                                                <a href="index.php?page=class_view&id=<?php echo $class['id']; ?>" class="btn-modern btn-modern-primary btn-sm">Voir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Students -->
            <div class="content-block content-block-small">
                <div class="content-block-header">
                    <h3 class="content-block-title">Top Élèves</h3>
                </div>
                <div class="content-block-body">
                    <?php if (empty($top_students)): ?>
                        <p class="empty-text">Aucun élève pour le moment.</p>
                    <?php else: ?>
                        <div class="student-list">
                            <?php foreach ($top_students as $index => $student_data): ?>
                                <div class="student-item <?php echo $index < count($top_students) - 1 ? 'bordered' : ''; ?>">
                                    <div class="student-info">
                                        <div class="student-name"><?php echo htmlspecialchars($student_data['nom']); ?></div>
                                        <div class="student-class"><?php echo htmlspecialchars($student_data['classe_nom']); ?></div>
                                    </div>
                                    <span class="badge badge-success"><?php echo $student_data['points']; ?> pts</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="content-block content-block-full">
            <div class="content-block-header">
                <h3 class="content-block-title">Activité récente (7 derniers jours)</h3>
                <a href="index.php?page=points_log" class="btn-modern btn-modern-secondary">Voir tout</a>
            </div>
            <div class="content-block-body">
                <?php if (empty($activities)): ?>
                    <p class="empty-text">Aucune activité récente.</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($activities as $index => $activity): ?>
                            <div class="activity-item <?php echo $index < count($activities) - 1 ? 'bordered' : ''; ?>">
                                <div class="activity-info">
                                    <div class="activity-name"><?php echo htmlspecialchars($activity['eleve_nom']); ?></div>
                                    <div class="activity-reason"><?php echo htmlspecialchars($activity['raison']); ?> - <?php echo htmlspecialchars($activity['classe_nom']); ?></div>
                                    <div class="activity-date"><?php echo date('d/m/Y H:i', strtotime($activity['date_ajout'])); ?></div>
                                </div>
                                <span class="badge badge-success">+<?php echo $activity['points_ajoutes']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/pwa.js"></script>
</body>
</html>
