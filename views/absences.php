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
$changes_made = 0;
$is_resubmission = false;

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected class from GET or POST
$selected_class_id = 0;
$students = [];
$selected_class_name = '';
$existing_statuses = [];
$today_submission = null;

if (isset($_GET['class_id'])) {
    $selected_class_id = intval($_GET['class_id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_presences'])) {
    $selected_class_id = intval($_POST['class_id'] ?? 0);
} elseif (!empty($classes)) {
    // Auto-select first class
    $selected_class_id = $classes[0]['id'];
}

// If a class is selected, get its students
if ($selected_class_id > 0) {
    $valid_class = false;
    foreach ($classes as $c) {
        if ($c['id'] == $selected_class_id) {
            $valid_class = true;
            $selected_class_name = $c['nom'];
            break;
        }
    }
    
    if ($valid_class) {
        $students_stmt = $student->getByClass($selected_class_id);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get existing statuses for today
        $today = date('Y-m-d');
        $existing_query = "SELECT student_id, statut FROM student_absences 
                          WHERE class_id = :class_id AND absence_date = :date";
        $existing_stmt = $db->prepare($existing_query);
        $existing_stmt->execute([':class_id' => $selected_class_id, ':date' => $today]);
        while ($row = $existing_stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing_statuses[$row['student_id']] = $row['statut'];
        }
        
        // Check if there's already a submission for today
        $submission_query = "SELECT * FROM absence_submissions 
                            WHERE class_id = :class_id AND submission_date = :date";
        $submission_stmt = $db->prepare($submission_query);
        $submission_stmt->execute([':class_id' => $selected_class_id, ':date' => $today]);
        $today_submission = $submission_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($today_submission) {
            $is_resubmission = true;
        }
    } else {
        $error = "Classe non autorisée.";
        $selected_class_id = 0;
    }
}

// Handle save presences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_presences']) && $selected_class_id > 0 && !empty($students)) {
    $date = date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    
    $present_count = 0;
    $absent_count = 0;
    $late_count = 0;
    $justified_count = 0;
    $changes_made = 0;

    try {
        $db->beginTransaction();
        
        $submission_query = "INSERT INTO absence_submissions (class_id, submission_date, total_students, submitted_by)
                            VALUES (:class_id, :date, :total, :teacher_id)
                            ON DUPLICATE KEY UPDATE 
                            total_students = VALUES(total_students),
                            updated_at = NOW()";
        $submission_stmt = $db->prepare($submission_query);
        $submission_stmt->execute([
            ':class_id' => $selected_class_id,
            ':date' => $date,
            ':total' => count($students),
            ':teacher_id' => $_SESSION['teacher_id']
        ]);
        
        $get_submission = $db->prepare("SELECT id FROM absence_submissions WHERE class_id = :class_id AND submission_date = :date");
        $get_submission->execute([':class_id' => $selected_class_id, ':date' => $date]);
        $submission_id = $get_submission->fetchColumn();

        foreach ($students as $s) {
            $student_id = $s['id'];
            $new_status = $statuses[$student_id] ?? 'present';
            $old_status = $existing_statuses[$student_id] ?? null;
            
            switch ($new_status) {
                case 'present': $present_count++; break;
                case 'absent': $absent_count++; break;
                case 'late': $late_count++; break;
                case 'justified': $justified_count++; break;
            }
            
            if ($old_status !== $new_status) {
                $query = "INSERT INTO student_absences (student_id, class_id, absence_date, statut, created_by)
                          VALUES (:student_id, :class_id, :absence_date, :statut, :created_by)
                          ON DUPLICATE KEY UPDATE statut = VALUES(statut), created_by = VALUES(created_by)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':student_id' => $student_id,
                    ':class_id' => $selected_class_id,
                    ':absence_date' => $date,
                    ':statut' => $new_status,
                    ':created_by' => $_SESSION['teacher_id'],
                ]);
                
                $log_query = "INSERT INTO absence_change_log (submission_id, student_id, old_status, new_status, changed_by)
                             VALUES (:submission_id, :student_id, :old_status, :new_status, :changed_by)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    ':submission_id' => $submission_id,
                    ':student_id' => $student_id,
                    ':old_status' => $old_status,
                    ':new_status' => $new_status,
                    ':changed_by' => $_SESSION['teacher_id']
                ]);
                
                $changes_made++;
            }
        }
        
        $update_counts = $db->prepare("UPDATE absence_submissions SET 
                                       present_count = :present, 
                                       absent_count = :absent, 
                                       late_count = :late, 
                                       justified_count = :justified 
                                       WHERE id = :id");
        $update_counts->execute([
            ':present' => $present_count,
            ':absent' => $absent_count,
            ':late' => $late_count,
            ':justified' => $justified_count,
            ':id' => $submission_id
        ]);
        
        $db->commit();
        
        // Refresh existing statuses after save
        $existing_statuses = [];
        $refresh_existing = $db->prepare("SELECT student_id, statut FROM student_absences WHERE class_id = :class_id AND absence_date = :date");
        $refresh_existing->execute([':class_id' => $selected_class_id, ':date' => $date]);
        while ($row = $refresh_existing->fetch(PDO::FETCH_ASSOC)) {
            $existing_statuses[$row['student_id']] = $row['statut'];
        }
        
        $refresh_submission = $db->prepare("SELECT * FROM absence_submissions WHERE class_id = :class_id AND submission_date = :date");
        $refresh_submission->execute([':class_id' => $selected_class_id, ':date' => $today]);
        $today_submission = $refresh_submission->fetch(PDO::FETCH_ASSOC);
        $is_resubmission = true;
        
        if ($changes_made > 0) {
            $message = "Présences enregistrées avec succès! ($changes_made modification(s))";
        } else {
            $message = "Aucune modification détectée.";
        }
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}

// Get submission history for selected class
$submission_history = [];
if ($selected_class_id > 0) {
    try {
        $history_query = "SELECT s.*, 
                          (SELECT COUNT(*) FROM absence_change_log WHERE submission_id = s.id) as changes_count
                          FROM absence_submissions s 
                          WHERE s.class_id = :class_id 
                          ORDER BY s.submission_date DESC 
                          LIMIT 7";
        $history_stmt = $db->prepare($history_query);
        $history_stmt->execute([':class_id' => $selected_class_id]);
        $submission_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $submission_history = [];
    }
}

// Compute live stats
$stat_present = count(array_filter($existing_statuses, fn($s) => $s === 'present'));
$stat_absent = count(array_filter($existing_statuses, fn($s) => $s === 'absent'));
$stat_late = count(array_filter($existing_statuses, fn($s) => $s === 'late'));
$stat_justified = count(array_filter($existing_statuses, fn($s) => $s === 'justified'));
$stat_unmarked = count($students) - count($existing_statuses);
$stat_present += $stat_unmarked;

// Day names for display
$day_names_fr = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$month_names_fr = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$today_day = $day_names_fr[date('w')];
$today_date = date('d') . ' ' . $month_names_fr[intval(date('m'))] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Gestion des Absences - No9ati</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background: #f8f9fb; }

        /* Searchable dropdown */
        .sd-wrap { position: relative; min-width: 260px; max-width: 320px; }
        .sd-toggle {
            display: flex; align-items: center; gap: 8px; width: 100%;
            padding: 8px 14px; border: 1px solid rgba(0,0,0,0.12); border-radius: 10px;
            background: #fff; cursor: pointer; font-size: 13px; font-weight: 500; color: #1c1c1c;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .sd-toggle:hover { border-color: rgba(0,0,0,0.25); }
        .sd-toggle:focus, .sd-wrap.open .sd-toggle {
            border-color: #1c1c1c; box-shadow: 0 0 0 3px rgba(0,0,0,0.06); outline: none;
        }
        .sd-toggle .sd-icon { margin-left: auto; color: rgba(0,0,0,0.3); transition: transform 0.2s; font-size: 11px; }
        .sd-wrap.open .sd-toggle .sd-icon { transform: rotate(180deg); }
        .sd-panel {
            display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            background: #fff; border: 1px solid rgba(0,0,0,0.1); border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1); z-index: 100; overflow: hidden;
            max-height: 260px;
        }
        .sd-wrap.open .sd-panel { display: block; }
        .sd-search {
            width: 100%; padding: 10px 14px; border: none; border-bottom: 1px solid rgba(0,0,0,0.06);
            font-size: 13px; outline: none; background: transparent; color: #1c1c1c;
        }
        .sd-search::placeholder { color: rgba(0,0,0,0.3); }
        .sd-list { max-height: 200px; overflow-y: auto; padding: 4px 0; }
        .sd-list::-webkit-scrollbar { width: 4px; }
        .sd-list::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 4px; }
        .sd-opt {
            display: flex; align-items: center; gap: 8px; padding: 8px 14px;
            font-size: 13px; color: #555; cursor: pointer; transition: background 0.1s;
            text-decoration: none;
        }
        .sd-opt:hover { background: rgba(0,0,0,0.04); color: #1c1c1c; }
        .sd-opt.active { background: rgba(0,0,0,0.06); color: #1c1c1c; font-weight: 600; }
        .sd-opt.active::before { content: '\2713'; font-size: 11px; color: #22c55e; font-weight: 700; }
        .sd-opt.hidden { display: none; }
        .sd-empty { padding: 16px 14px; text-align: center; font-size: 12px; color: rgba(0,0,0,0.3); }

        /* Stats bar */
        .stat-mini {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 8px;
            font-size: 13px; font-weight: 600;
        }
        .stat-mini .dot {
            width: 8px; height: 8px; border-radius: 50%;
        }

        /* Student rows */
        .student-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-radius: 12px;
            border: 1.5px solid rgba(0,0,0,0.06); background: #fff;
            transition: all 0.15s ease; gap: 12px;
        }
        .student-item:hover { border-color: rgba(0,0,0,0.12); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .student-item.is-absent { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.02); }
        .student-item.is-late { border-color: rgba(245,158,11,0.3); background: rgba(245,158,11,0.02); }
        .student-item.is-justified { border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.02); }

        .student-avatar {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; flex-shrink: 0;
        }

        /* Segmented status control */
        .status-seg {
            display: inline-flex; border-radius: 10px; overflow: hidden;
            border: 1.5px solid rgba(0,0,0,0.08); background: rgba(0,0,0,0.02);
            flex-shrink: 0;
        }
        .status-seg .seg-btn {
            padding: 6px 10px; font-size: 12px; font-weight: 600;
            border: none; background: transparent; color: rgba(0,0,0,0.35);
            cursor: pointer; transition: all 0.15s ease;
            display: flex; align-items: center; gap: 4px;
            position: relative; white-space: nowrap;
        }
        .status-seg .seg-btn:not(:last-child)::after {
            content: ''; position: absolute; right: 0; top: 20%; height: 60%;
            width: 1px; background: rgba(0,0,0,0.08);
        }
        .status-seg .seg-btn:hover { color: rgba(0,0,0,0.6); background: rgba(0,0,0,0.03); }
        .status-seg .seg-btn.active-present { background: #22c55e; color: #fff; }
        .status-seg .seg-btn.active-present::after { display: none; }
        .status-seg .seg-btn.active-absent { background: #ef4444; color: #fff; }
        .status-seg .seg-btn.active-absent::after { display: none; }
        .status-seg .seg-btn.active-late { background: #f59e0b; color: #fff; }
        .status-seg .seg-btn.active-late::after { display: none; }
        .status-seg .seg-btn.active-justified { background: #6366f1; color: #fff; }
        .status-seg .seg-btn.active-justified::after { display: none; }

        /* Responsive: stack on mobile */
        @media (max-width: 640px) {
            .student-item { flex-wrap: wrap; gap: 8px; }
            .status-seg { width: 100%; }
            .status-seg .seg-btn { flex: 1; justify-content: center; padding: 8px 6px; }
            .seg-label { display: none; }
        }
        @media (min-width: 641px) {
            .seg-icon-only { display: none; }
        }

        /* History mini table */
        .history-mini td, .history-mini th {
            padding: 10px 12px; font-size: 13px; vertical-align: middle;
        }
        .history-mini th {
            font-weight: 600; color: rgba(0,0,0,0.45);
            text-transform: uppercase; letter-spacing: 0.3px; font-size: 11px;
            border-bottom: 2px solid rgba(0,0,0,0.06);
        }
        .history-mini tr { border-bottom: 1px solid rgba(0,0,0,0.04); }
        .history-mini tr:last-child { border-bottom: none; }

        /* Custom scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.25); }

        /* Card */
        .abs-card {
            border-radius: 16px; border: 1px solid rgba(0,0,0,0.06);
            background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        /* Toast */
        .abs-toast {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            padding: 14px 22px; border-radius: 12px; display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 500; color: #fff;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            animation: slideInToast 0.3s ease;
        }
        .abs-toast.success { background: #22c55e; }
        .abs-toast.error { background: #ef4444; }
        @keyframes slideInToast {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include 'views/partials/sidebar.php'; ?>
    
    <div class="main-content d-flex flex-column" style="padding-top: 88px;">
        <nav class="navbar navbar-expand-lg" style="background: #fff; position: fixed; left: 212px; right: 0; top: 0; z-index: 1020; height: 68px; border-bottom: 1px solid rgba(0,0,0,0.08);">
            <div class="container-fluid px-4">
                <button class="btn d-lg-none me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1c1c1c" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div>
                    <h1 style="font-size: 15px; font-weight: 600; color: #1c1c1c; margin: 0; line-height: 1.2;">Gestion des Absences</h1>
                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;"><?php echo $today_day . ', ' . $today_date; ?></p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?page=absence_history<?php echo $selected_class_id > 0 ? '&class_id='.$selected_class_id : ''; ?>" 
                       class="btn btn-sm" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px; font-size: 13px; color: #1c1c1c; padding: 6px 12px; text-decoration: none;">
                        <i class="bi bi-clock-history me-1"></i> Historique
                    </a>
                    <div class="d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 13px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 20px 24px;">
            <?php if (!empty($error)): ?>
                <script>var _toastError = <?= json_encode($error) ?>;</script>
            <?php endif; ?>

            <!-- Class Selection Dropdown -->
            <?php if (!empty($classes)): ?>
            <div class="d-flex align-items-center gap-2 mb-3">
                <span style="font-size: 12px; font-weight: 600; color: rgba(0,0,0,0.35); text-transform: uppercase; letter-spacing: 0.5px; margin-right: 4px;">Classe</span>
                <div class="sd-wrap" id="sdClassFilter">
                    <button type="button" class="sd-toggle" onclick="sdToggle('sdClassFilter')">
                        <?php
                        $selected_class_label = 'Sélectionner une classe';
                        foreach ($classes as $c) {
                            if ($selected_class_id == $c['id']) { $selected_class_label = htmlspecialchars($c['nom']); break; }
                        }
                        echo $selected_class_label;
                        ?>
                        <span class="sd-icon">&#9662;</span>
                    </button>
                    <div class="sd-panel">
                        <input type="text" class="sd-search" placeholder="Rechercher une classe..." oninput="sdFilter(this)">
                        <div class="sd-list">
                            <?php foreach ($classes as $c): ?>
                                <a href="index.php?page=absences&class_id=<?php echo $c['id']; ?>"
                                   class="sd-opt <?php echo $selected_class_id == $c['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                    <span style="margin-left:auto;font-size:11px;color:rgba(0,0,0,0.35);"><?php echo $classroom->getStudentCount($c['id']); ?> élèves</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($classes)): ?>
                <!-- No classes -->
                <div class="abs-card p-5 text-center">
                    <i class="bi bi-building" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Aucune classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin-bottom: 16px;">Créez une classe pour commencer à gérer les absences.</p>
                    <a href="index.php?page=manage_classes" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 8px 20px; font-size: 14px;">
                        <i class="bi bi-plus-lg me-1"></i> Créer une classe
                    </a>
                </div>
            <?php elseif ($selected_class_id == 0): ?>
                <!-- No class selected -->
                <div class="abs-card p-5 text-center">
                    <i class="bi bi-arrow-up-circle" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Sélectionnez une classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Choisissez une classe ci-dessus pour marquer les présences.</p>
                </div>
            <?php else: ?>
                <!-- Stats Bar -->
                <?php if (!empty($students)): ?>
                <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                    <div class="stat-mini" style="background: rgba(34,197,94,0.1); color: #16a34a;">
                        <span class="dot" style="background: #22c55e;"></span>
                        <span id="statPresent"><?php echo $stat_present; ?></span> Présents
                    </div>
                    <div class="stat-mini" style="background: rgba(239,68,68,0.1); color: #dc2626;">
                        <span class="dot" style="background: #ef4444;"></span>
                        <span id="statAbsent"><?php echo $stat_absent; ?></span> Absents
                    </div>
                    <div class="stat-mini" style="background: rgba(245,158,11,0.1); color: #d97706;">
                        <span class="dot" style="background: #f59e0b;"></span>
                        <span id="statLate"><?php echo $stat_late; ?></span> Retards
                    </div>
                    <div class="stat-mini" style="background: rgba(99,102,241,0.1); color: #4f46e5;">
                        <span class="dot" style="background: #6366f1;"></span>
                        <span id="statJustified"><?php echo $stat_justified; ?></span> Justifiés
                    </div>
                    <?php if ($is_resubmission): ?>
                        <span style="font-size: 12px; color: #d97706; background: rgba(245,158,11,0.1); padding: 5px 12px; border-radius: 8px; font-weight: 500;">
                            <i class="bi bi-arrow-repeat me-1"></i>Déjà enregistré aujourd'hui
                        </span>
                    <?php endif; ?>
                    <div class="ms-auto d-flex gap-2">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute" style="left: 10px; top: 50%; transform: translateY(-50%); font-size: 14px; color: rgba(0,0,0,0.3);"></i>
                            <input type="text" id="studentSearch" class="form-control form-control-sm" placeholder="Rechercher..." 
                                   style="padding-left: 32px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); font-size: 13px; width: 180px; height: 34px;">
                        </div>
                        <button type="button" id="markAllPresentBtn" class="btn btn-sm" style="background: #22c55e; color: #fff; border-radius: 8px; font-size: 13px; font-weight: 500; padding: 6px 14px; border: none; white-space: nowrap;">
                            <i class="bi bi-check-all me-1"></i>Tous présents
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attendance Form -->
                <form method="POST" action="index.php?page=absences&class_id=<?php echo $selected_class_id; ?>" id="attendanceForm">
                    <input type="hidden" name="save_presences" value="1">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                    <div class="abs-card">
                        <?php if (empty($students)): ?>
                            <div class="p-5 text-center">
                                <i class="bi bi-people" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                                <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 16px 0 0;">Aucun élève dans cette classe.</p>
                            </div>
                        <?php else: ?>
                            <div class="p-3 custom-scroll" style="max-height: calc(100vh - 310px); overflow-y: auto;">
                                <div class="d-flex flex-column gap-2" id="studentsList">
                                    <?php foreach ($students as $idx => $s): ?>
                                        <?php 
                                        $current_status = $existing_statuses[$s['id']] ?? 'present';
                                        $avatar_colors = [
                                            'present' => ['rgba(34,197,94,0.1)', '#22c55e'],
                                            'absent' => ['rgba(239,68,68,0.1)', '#ef4444'],
                                            'late' => ['rgba(245,158,11,0.1)', '#f59e0b'],
                                            'justified' => ['rgba(99,102,241,0.1)', '#6366f1'],
                                        ];
                                        $ac = $avatar_colors[$current_status];
                                        ?>
                                        <div class="student-item <?php echo $current_status !== 'present' ? 'is-'.$current_status : ''; ?>" 
                                             data-name="<?php echo strtolower($s['nom']); ?>" data-student-id="<?php echo $s['id']; ?>">
                                            
                                            <div class="d-flex align-items-center gap-3" style="min-width: 0;">
                                                <span style="font-size: 12px; color: rgba(0,0,0,0.25); font-weight: 600; width: 20px; text-align: center; flex-shrink: 0;"><?php echo $idx + 1; ?></span>
                                                <div class="student-avatar" id="avatar-<?php echo $s['id']; ?>" 
                                                     style="background: <?php echo $ac[0]; ?>; color: <?php echo $ac[1]; ?>;">
                                                    <?php echo strtoupper(substr($s['nom'], 0, 2)); ?>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <p style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <?php echo htmlspecialchars($s['nom']); ?>
                                                    </p>
                                                    <?php if (!empty($s['totalHeures'])): ?>
                                                        <p style="font-size: 11px; color: rgba(0,0,0,0.35); margin: 0;"><?php echo intval($s['totalHeures']); ?>h d'absence cumulée</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="status-seg" data-student-id="<?php echo $s['id']; ?>">
                                                <button type="button" class="seg-btn <?php echo $current_status === 'present' ? 'active-present' : ''; ?>" data-status="present" title="Présent">
                                                    <i class="bi bi-check-lg seg-icon-only"></i>
                                                    <span class="seg-label">Présent</span>
                                                </button>
                                                <button type="button" class="seg-btn <?php echo $current_status === 'absent' ? 'active-absent' : ''; ?>" data-status="absent" title="Absent">
                                                    <i class="bi bi-x-lg seg-icon-only"></i>
                                                    <span class="seg-label">Absent</span>
                                                </button>
                                                <button type="button" class="seg-btn <?php echo $current_status === 'late' ? 'active-late' : ''; ?>" data-status="late" title="Retard">
                                                    <i class="bi bi-clock seg-icon-only"></i>
                                                    <span class="seg-label">Retard</span>
                                                </button>
                                                <button type="button" class="seg-btn <?php echo $current_status === 'justified' ? 'active-justified' : ''; ?>" data-status="justified" title="Justifié">
                                                    <i class="bi bi-file-earmark-check seg-icon-only"></i>
                                                    <span class="seg-label">Justifié</span>
                                                </button>
                                            </div>
                                            <input type="hidden" name="status[<?php echo $s['id']; ?>]" id="status-<?php echo $s['id']; ?>" value="<?php echo $current_status; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Save Footer -->
                            <div class="d-flex align-items-center justify-content-between p-3" style="border-top: 1px solid rgba(0,0,0,0.06);">
                                <span style="font-size: 13px; color: rgba(0,0,0,0.45);">
                                    <?php echo count($students); ?> élèves &middot; <?php echo htmlspecialchars($selected_class_name); ?>
                                </span>
                                <button type="submit" class="btn" style="background: #1c1c1c; color: #fff; border-radius: 10px; padding: 9px 22px; font-size: 14px; font-weight: 500; border: none;">
                                    <i class="bi bi-check2-circle me-1"></i>
                                    <?php echo $is_resubmission ? 'Mettre à jour' : 'Enregistrer'; ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Recent Submissions -->
                <?php if (!empty($submission_history)): ?>
                <div class="abs-card mt-3">
                    <div class="p-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <h3 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">
                            <i class="bi bi-clock-history me-2" style="color: rgba(0,0,0,0.3);"></i>Dernières soumissions
                        </h3>
                        <a href="index.php?page=absence_history&class_id=<?php echo $selected_class_id; ?>" 
                           style="font-size: 12px; color: rgba(0,0,0,0.4); text-decoration: none;">Voir tout &rarr;</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table history-mini mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-center">P</th>
                                    <th class="text-center">A</th>
                                    <th class="text-center">R</th>
                                    <th class="text-center">J</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submission_history as $sub): ?>
                                <tr>
                                    <td style="color: #1c1c1c; font-weight: 500;">
                                        <?php 
                                        $sub_date = new DateTime($sub['submission_date']);
                                        echo $sub_date->format('d/m');
                                        $sub_day = $day_names_fr[$sub_date->format('w')];
                                        echo ' <span style="color:rgba(0,0,0,0.3);font-size:11px;">' . substr($sub_day, 0, 3) . '</span>';
                                        ?>
                                        <?php if ($sub['submission_date'] === date('Y-m-d')): ?>
                                            <span style="background: rgba(34,197,94,0.15); color: #22c55e; font-size: 10px; padding: 1px 6px; border-radius: 4px; margin-left: 4px;">Auj.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="color: #22c55e; font-weight: 600;"><?php echo $sub['present_count'] ?? 0; ?></td>
                                    <td class="text-center" style="color: #ef4444; font-weight: 600;"><?php echo $sub['absent_count'] ?? 0; ?></td>
                                    <td class="text-center" style="color: #f59e0b; font-weight: 600;"><?php echo $sub['late_count'] ?? 0; ?></td>
                                    <td class="text-center" style="color: #6366f1; font-weight: 600;"><?php echo $sub['justified_count'] ?? 0; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Toast -->
    <?php if (!empty($message)): ?>
    <div class="abs-toast success" id="absToast">
        <i class="bi bi-check-circle-fill"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #fff; opacity: 0.7; cursor: pointer; margin-left: 8px;"><i class="bi bi-x-lg"></i></button>
    </div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusColors = {
            present:   { bg: 'rgba(34,197,94,0.1)',  color: '#22c55e', cls: 'active-present', itemCls: '' },
            absent:    { bg: 'rgba(239,68,68,0.1)',   color: '#ef4444', cls: 'active-absent',  itemCls: 'is-absent' },
            late:      { bg: 'rgba(245,158,11,0.1)',  color: '#f59e0b', cls: 'active-late',    itemCls: 'is-late' },
            justified: { bg: 'rgba(99,102,241,0.1)',  color: '#6366f1', cls: 'active-justified', itemCls: 'is-justified' }
        };

        // Status segmented control click
        document.querySelectorAll('.status-seg').forEach(seg => {
            seg.querySelectorAll('.seg-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = seg.dataset.studentId;
                    const newStatus = this.dataset.status;
                    setStudentStatus(studentId, newStatus);
                });
            });
        });

        function setStudentStatus(studentId, status) {
            const input = document.getElementById('status-' + studentId);
            const item = document.querySelector('.student-item[data-student-id="' + studentId + '"]');
            const seg = item.querySelector('.status-seg');
            const avatar = document.getElementById('avatar-' + studentId);
            const info = statusColors[status];

            input.value = status;

            seg.querySelectorAll('.seg-btn').forEach(b => {
                b.className = 'seg-btn';
            });
            seg.querySelector('[data-status="' + status + '"]').classList.add(info.cls);

            item.className = 'student-item' + (info.itemCls ? ' ' + info.itemCls : '');

            avatar.style.background = info.bg;
            avatar.style.color = info.color;

            updateStats();
        }

        // Mark All Present
        const markAllBtn = document.getElementById('markAllPresentBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.student-item').forEach(item => {
                    const studentId = item.dataset.studentId;
                    setStudentStatus(studentId, 'present');
                });
            });
        }

        // Search
        const searchInput = document.getElementById('studentSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const q = this.value.toLowerCase();
                document.querySelectorAll('.student-item').forEach(item => {
                    const name = item.dataset.name;
                    item.style.display = (!q || name.includes(q)) ? 'flex' : 'none';
                });
            });
        }

        // Live stats update
        function updateStats() {
            const counts = { present: 0, absent: 0, late: 0, justified: 0 };
            document.querySelectorAll('input[name^="status["]').forEach(input => {
                const s = input.value;
                if (counts.hasOwnProperty(s)) counts[s]++;
            });
            const el = (id) => document.getElementById(id);
            if (el('statPresent')) el('statPresent').textContent = counts.present;
            if (el('statAbsent'))  el('statAbsent').textContent = counts.absent;
            if (el('statLate'))    el('statLate').textContent = counts.late;
            if (el('statJustified')) el('statJustified').textContent = counts.justified;
        }

        // Auto-dismiss toast
        const toast = document.getElementById('absToast');
        if (toast) {
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-12px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Keyboard shortcut: Ctrl+S to save
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const form = document.getElementById('attendanceForm');
                if (form) form.submit();
            }
        });
    });
    </script>

    <script>
    // Searchable Dropdown Logic
    function sdToggle(id) {
        const wrap = document.getElementById(id);
        const wasOpen = wrap.classList.contains('open');
        document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
        if (!wasOpen) {
            wrap.classList.add('open');
            const input = wrap.querySelector('.sd-search');
            if (input) { input.value = ''; sdFilter(input); input.focus(); }
        }
    }
    function sdFilter(input) {
        const query = input.value.toLowerCase().trim();
        const list = input.closest('.sd-panel').querySelector('.sd-list');
        const opts = list.querySelectorAll('.sd-opt');
        let visible = 0;
        opts.forEach(opt => {
            const text = opt.textContent.toLowerCase();
            const match = !query || text.includes(query);
            opt.classList.toggle('hidden', !match);
            if (match) visible++;
        });
        let emptyEl = list.querySelector('.sd-empty');
        if (visible === 0) {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.className = 'sd-empty';
                emptyEl.textContent = 'Aucun résultat';
                list.appendChild(emptyEl);
            }
            emptyEl.style.display = '';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.sd-wrap')) {
            document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
