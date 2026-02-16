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

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filters
$selected_class_id = intval($_GET['class_id'] ?? 0);
$selected_student_id = intval($_GET['student_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$view_mode = $_GET['view'] ?? 'group';
$date_preset = $_GET['preset'] ?? '';

// Handle date presets
if ($date_preset) {
    switch ($date_preset) {
        case 'today':
            $date_from = date('Y-m-d');
            $date_to = date('Y-m-d');
            break;
        case 'week':
            $date_from = date('Y-m-d', strtotime('monday this week'));
            $date_to = date('Y-m-d');
            break;
        case 'month':
            $date_from = date('Y-m-01');
            $date_to = date('Y-m-d');
            break;
        case 'all':
            $date_from = date('Y-01-01');
            $date_to = date('Y-m-d');
            break;
    }
}

$selected_class_name = '';
$students_list = [];
$history_data = [];
$student_summary = [];
$selected_student_name = '';

// Validate class ownership
if ($selected_class_id > 0) {
    $valid_class = false;
    foreach ($classes as $c) {
        if ($c['id'] == $selected_class_id) {
            $valid_class = true;
            $selected_class_name = $c['nom'];
            break;
        }
    }
    if (!$valid_class) {
        $selected_class_id = 0;
    }
} elseif (!empty($classes)) {
    // Auto-select first class
    $selected_class_id = $classes[0]['id'];
    $selected_class_name = $classes[0]['nom'];
}

// Get students for selected class
if ($selected_class_id > 0) {
    $students_stmt = $student->getByClass($selected_class_id);
    $students_list = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch history data
if ($selected_class_id > 0) {
    try {
        if ($view_mode === 'student' && $selected_student_id > 0) {
            $query = "SELECT sa.*, e.nom as student_name 
                      FROM student_absences sa 
                      JOIN eleves e ON sa.student_id = e.id 
                      WHERE sa.class_id = :class_id 
                        AND sa.student_id = :student_id
                        AND sa.absence_date BETWEEN :date_from AND :date_to
                      ORDER BY sa.absence_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':class_id' => $selected_class_id,
                ':student_id' => $selected_student_id,
                ':date_from' => $date_from,
                ':date_to' => $date_to
            ]);
            $history_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($students_list as $s) {
                if ($s['id'] == $selected_student_id) {
                    $selected_student_name = $s['nom'];
                    break;
                }
            }
        } else {
            // Group history - summary per student
            $query = "SELECT e.id, e.nom as student_name,
                        COUNT(CASE WHEN sa.statut = 'absent' THEN 1 END) as absent_count,
                        COUNT(CASE WHEN sa.statut = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN sa.statut = 'late' THEN 1 END) as late_count,
                        COUNT(CASE WHEN sa.statut = 'justified' THEN 1 END) as justified_count,
                        COUNT(sa.id) as total_records
                      FROM eleves e
                      LEFT JOIN student_absences sa ON e.id = sa.student_id 
                        AND sa.class_id = :class_id
                        AND sa.absence_date BETWEEN :date_from AND :date_to
                      WHERE e.classe_id = :class_id2
                      GROUP BY e.id, e.nom
                      ORDER BY absent_count DESC, e.nom ASC";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':class_id' => $selected_class_id,
                ':class_id2' => $selected_class_id,
                ':date_from' => $date_from,
                ':date_to' => $date_to
            ]);
            $student_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $day_query = "SELECT sa.absence_date,
                            COUNT(CASE WHEN sa.statut = 'absent' THEN 1 END) as absent_count,
                            COUNT(CASE WHEN sa.statut = 'present' THEN 1 END) as present_count,
                            COUNT(CASE WHEN sa.statut = 'late' THEN 1 END) as late_count,
                            COUNT(sa.id) as total_records
                          FROM student_absences sa
                          WHERE sa.class_id = :class_id 
                            AND sa.absence_date BETWEEN :date_from AND :date_to
                          GROUP BY sa.absence_date
                          ORDER BY sa.absence_date DESC";
            $day_stmt = $db->prepare($day_query);
            $day_stmt->execute([
                ':class_id' => $selected_class_id,
                ':date_from' => $date_from,
                ':date_to' => $date_to
            ]);
            $history_data = $day_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $history_data = [];
        $student_summary = [];
    }
}

// Calculate totals
$total_absences = 0;
$total_lates = 0;
$total_presents = 0;
$total_justified = 0;
if (!empty($student_summary)) {
    foreach ($student_summary as $s) {
        $total_absences += $s['absent_count'];
        $total_lates += $s['late_count'];
        $total_presents += $s['present_count'];
        $total_justified += $s['justified_count'];
    }
}

$student_total_absent = 0;
$student_total_late = 0;
$student_total_present = 0;
$student_total_justified = 0;
if ($view_mode === 'student' && !empty($history_data)) {
    foreach ($history_data as $h) {
        switch ($h['statut'] ?? '') {
            case 'absent': $student_total_absent++; break;
            case 'late': $student_total_late++; break;
            case 'present': $student_total_present++; break;
            case 'justified': $student_total_justified++; break;
        }
    }
}

$day_names_fr = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$month_names_fr = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

// Build base URL for filters
function buildFilterUrl($params = []) {
    $base = [
        'page' => 'absence_history',
    ];
    return 'index.php?' . http_build_query(array_merge($base, $params));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Historique des Absences - No9ati</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background: #f8f9fb; }

        .abs-card {
            border-radius: 16px; border: 1px solid rgba(0,0,0,0.06);
            background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

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

        /* Preset chips */
        .preset-chip {
            padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 500;
            border: 1px solid rgba(0,0,0,0.08); background: #fff; color: #555;
            text-decoration: none; transition: all 0.15s ease;
        }
        .preset-chip:hover { border-color: rgba(0,0,0,0.2); color: #1c1c1c; }
        .preset-chip.active { background: #1c1c1c; color: #fff; border-color: #1c1c1c; }

        /* View toggle */
        .view-btn {
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            border: 1.5px solid rgba(0,0,0,0.08); background: #fff; color: #555;
            text-decoration: none; transition: all 0.15s ease;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .view-btn:hover { border-color: rgba(0,0,0,0.2); color: #1c1c1c; }
        .view-btn.active { background: #1c1c1c; color: #fff; border-color: #1c1c1c; }

        /* Stats */
        .stat-box {
            border-radius: 12px; padding: 16px; text-align: center;
            border: 1px solid rgba(0,0,0,0.05); background: #fff;
            transition: transform 0.15s ease;
        }
        .stat-box:hover { transform: translateY(-2px); }
        .stat-box .stat-num { font-size: 26px; font-weight: 700; line-height: 1; }
        .stat-box .stat-label { font-size: 12px; color: rgba(0,0,0,0.45); margin-top: 4px; font-weight: 500; }

        /* Tables */
        .hist-table th {
            font-size: 11px; font-weight: 600; color: rgba(0,0,0,0.4);
            text-transform: uppercase; letter-spacing: 0.4px;
            padding: 10px 14px; border-bottom: 2px solid rgba(0,0,0,0.06);
        }
        .hist-table td {
            padding: 12px 14px; font-size: 13px; color: #1c1c1c;
            border-bottom: 1px solid rgba(0,0,0,0.04); vertical-align: middle;
        }
        .hist-table tr:last-child td { border-bottom: none; }
        .hist-table tr:hover td { background: rgba(0,0,0,0.01); }

        /* Absence rate bar */
        .rate-bar { height: 6px; border-radius: 3px; background: rgba(0,0,0,0.05); overflow: hidden; min-width: 60px; }
        .rate-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s ease; }

        /* Status badge */
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 600;
        }
        .status-badge .dot { width: 6px; height: 6px; border-radius: 50%; }

        /* Student avatar */
        .stu-avatar {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .filter-row { flex-direction: column; gap: 8px !important; }
            .filter-row .date-inputs { width: 100%; }
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
                    <h1 style="font-size: 15px; font-weight: 600; color: #1c1c1c; margin: 0; line-height: 1.2;">Historique des Absences</h1>
                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">
                        <?php 
                        $from_d = new DateTime($date_from);
                        $to_d = new DateTime($date_to);
                        echo $from_d->format('d/m') . ' — ' . $to_d->format('d/m/Y');
                        ?>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?page=absences<?php echo $selected_class_id > 0 ? '&class_id='.$selected_class_id : ''; ?>" 
                       class="btn btn-sm" style="background: rgba(0,0,0,0.04); border: none; border-radius: 8px; font-size: 13px; color: #1c1c1c; padding: 6px 12px; text-decoration: none;">
                        <i class="bi bi-clipboard-check me-1"></i> Absences du jour
                    </a>
                    <div class="d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 13px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 20px 24px;">
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
                                <a href="<?php echo buildFilterUrl(['class_id' => $c['id'], 'date_from' => $date_from, 'date_to' => $date_to, 'view' => $view_mode]); ?>"
                                   class="sd-opt <?php echo $selected_class_id == $c['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($selected_class_id > 0): ?>
            <!-- Filter Bar -->
            <div class="abs-card p-3 mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center filter-row">
                    <!-- Date presets -->
                    <div class="d-flex gap-1 align-items-center">
                        <span style="font-size: 11px; font-weight: 600; color: rgba(0,0,0,0.3); text-transform: uppercase; margin-right: 4px;">Période</span>
                        <?php
                        $presets = [
                            'today' => "Aujourd'hui",
                            'week' => 'Semaine',
                            'month' => 'Ce mois',
                            'all' => 'Tout',
                        ];
                        foreach ($presets as $pk => $pl): ?>
                            <a href="<?php echo buildFilterUrl(['class_id' => $selected_class_id, 'preset' => $pk, 'view' => $view_mode]); ?>" 
                               class="preset-chip <?php echo $date_preset === $pk ? 'active' : ''; ?>"><?php echo $pl; ?></a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Custom date range -->
                    <form method="GET" action="index.php" class="d-flex gap-2 align-items-center date-inputs ms-auto" id="dateFilterForm">
                        <input type="hidden" name="page" value="absence_history">
                        <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                        <?php if ($selected_student_id > 0): ?>
                            <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                        <?php endif; ?>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="form-control form-control-sm" 
                               style="border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); font-size: 12px; padding: 5px 8px; width: 130px;"
                               onchange="this.form.submit()">
                        <span style="font-size: 12px; color: rgba(0,0,0,0.3);">—</span>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="form-control form-control-sm" 
                               style="border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); font-size: 12px; padding: 5px 8px; width: 130px;"
                               onchange="this.form.submit()">
                    </form>

                    <!-- View toggle -->
                    <div class="d-flex gap-1">
                        <a href="<?php echo buildFilterUrl(['class_id' => $selected_class_id, 'date_from' => $date_from, 'date_to' => $date_to, 'view' => 'group']); ?>" 
                           class="view-btn <?php echo $view_mode === 'group' ? 'active' : ''; ?>">
                            <i class="bi bi-people-fill"></i> Groupe
                        </a>
                        <a href="<?php echo buildFilterUrl(['class_id' => $selected_class_id, 'date_from' => $date_from, 'date_to' => $date_to, 'view' => 'student']); ?>" 
                           class="view-btn <?php echo $view_mode === 'student' ? 'active' : ''; ?>">
                            <i class="bi bi-person-fill"></i> Élève
                        </a>
                    </div>
                </div>

                <?php if ($view_mode === 'student'): ?>
                <div class="mt-2 pt-2" style="border-top: 1px solid rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center gap-2">
                        <span style="font-size: 11px; font-weight: 600; color: rgba(0,0,0,0.3); text-transform: uppercase; margin-right: 4px;">Élève</span>
                        <div class="sd-wrap" id="sdStudentFilter">
                            <button type="button" class="sd-toggle" onclick="sdToggle('sdStudentFilter')">
                                <?php
                                $selected_student_label = 'Sélectionner un élève';
                                foreach ($students_list as $s) {
                                    if ($selected_student_id == $s['id']) { $selected_student_label = htmlspecialchars($s['nom']); break; }
                                }
                                echo $selected_student_label;
                                ?>
                                <span class="sd-icon">&#9662;</span>
                            </button>
                            <div class="sd-panel">
                                <input type="text" class="sd-search" placeholder="Rechercher un élève..." oninput="sdFilter(this)">
                                <div class="sd-list">
                                    <?php foreach ($students_list as $s): ?>
                                        <a href="<?php echo buildFilterUrl(['class_id' => $selected_class_id, 'student_id' => $s['id'], 'date_from' => $date_from, 'date_to' => $date_to, 'view' => 'student']); ?>"
                                           class="sd-opt <?php echo $selected_student_id == $s['id'] ? 'active' : ''; ?>">
                                            <?php echo htmlspecialchars($s['nom']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($view_mode === 'group'): ?>
                <!-- GROUP VIEW -->
                
                <!-- Stats -->
                <?php if (!empty($student_summary)): ?>
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #22c55e;"><?php echo $total_presents; ?></div>
                            <div class="stat-label">Présences</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #ef4444;"><?php echo $total_absences; ?></div>
                            <div class="stat-label">Absences</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #f59e0b;"><?php echo $total_lates; ?></div>
                            <div class="stat-label">Retards</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #1c1c1c;"><?php echo count($history_data); ?></div>
                            <div class="stat-label">Jours enregistrés</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Student Summary Table -->
                <div class="abs-card mb-3">
                    <div class="p-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <h3 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">
                            <i class="bi bi-people me-2" style="color: rgba(0,0,0,0.3);"></i>Résumé par élève
                        </h3>
                        <span style="font-size: 12px; color: rgba(0,0,0,0.35);"><?php echo htmlspecialchars($selected_class_name); ?></span>
                    </div>

                    <?php if (empty($student_summary)): ?>
                        <div class="p-5 text-center">
                            <i class="bi bi-calendar-x" style="font-size: 40px; color: rgba(0,0,0,0.1);"></i>
                            <p style="font-size: 14px; color: rgba(0,0,0,0.35); margin: 12px 0 0;">Aucune donnée pour cette période.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table hist-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th class="text-center">P</th>
                                        <th class="text-center">A</th>
                                        <th class="text-center">R</th>
                                        <th class="text-center">J</th>
                                        <th>Taux d'absence</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student_summary as $s): ?>
                                    <?php 
                                        $total = $s['total_records'] > 0 ? $s['total_records'] : 1;
                                        $absence_rate = round(($s['absent_count'] / $total) * 100);
                                        $bar_color = $absence_rate > 30 ? '#ef4444' : ($absence_rate > 15 ? '#f59e0b' : '#22c55e');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="stu-avatar" style="background: <?php echo $s['absent_count'] > 0 ? 'rgba(239,68,68,0.1)' : 'rgba(34,197,94,0.1)'; ?>; color: <?php echo $s['absent_count'] > 0 ? '#ef4444' : '#22c55e'; ?>;">
                                                    <?php echo strtoupper(substr($s['student_name'], 0, 2)); ?>
                                                </div>
                                                <span style="font-weight: 500; font-size: 13px;"><?php echo htmlspecialchars($s['student_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-center" style="color: #22c55e; font-weight: 600;"><?php echo $s['present_count']; ?></td>
                                        <td class="text-center" style="color: #ef4444; font-weight: 600;"><?php echo $s['absent_count']; ?></td>
                                        <td class="text-center" style="color: #f59e0b; font-weight: 600;"><?php echo $s['late_count']; ?></td>
                                        <td class="text-center" style="color: #6366f1; font-weight: 600;"><?php echo $s['justified_count']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rate-bar flex-grow-1">
                                                    <div class="rate-bar-fill" style="width: <?php echo $absence_rate; ?>%; background: <?php echo $bar_color; ?>;"></div>
                                                </div>
                                                <span style="font-size: 11px; font-weight: 600; color: <?php echo $bar_color; ?>; min-width: 30px;"><?php echo $absence_rate; ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="<?php echo buildFilterUrl(['class_id' => $selected_class_id, 'student_id' => $s['id'], 'date_from' => $date_from, 'date_to' => $date_to, 'view' => 'student']); ?>" 
                                               class="btn btn-sm" style="background: rgba(0,0,0,0.04); border-radius: 6px; font-size: 11px; color: #555; padding: 3px 8px;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Day-by-Day History -->
                <?php if (!empty($history_data)): ?>
                <div class="abs-card">
                    <div class="p-3" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <h3 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">
                            <i class="bi bi-calendar3 me-2" style="color: rgba(0,0,0,0.3);"></i>Jour par jour
                        </h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table hist-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-center">Présents</th>
                                    <th class="text-center">Absents</th>
                                    <th class="text-center">Retards</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history_data as $day): ?>
                                <tr>
                                    <td>
                                        <?php 
                                            $d = new DateTime($day['absence_date']);
                                            echo '<span style="font-weight:500;">' . $d->format('d/m/Y') . '</span>';
                                            echo ' <span style="color:rgba(0,0,0,0.3);font-size:11px;">' . substr($day_names_fr[$d->format('w')], 0, 3) . '</span>';
                                        ?>
                                        <?php if ($day['absence_date'] === date('Y-m-d')): ?>
                                            <span style="background:rgba(34,197,94,0.15);color:#22c55e;font-size:10px;padding:1px 6px;border-radius:4px;margin-left:4px;">Auj.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><span style="color: #22c55e; font-weight: 600;"><?php echo $day['present_count']; ?></span></td>
                                    <td class="text-center"><span style="color: #ef4444; font-weight: 600;"><?php echo $day['absent_count']; ?></span></td>
                                    <td class="text-center"><span style="color: #f59e0b; font-weight: 600;"><?php echo $day['late_count']; ?></span></td>
                                    <td class="text-center"><span style="color: rgba(0,0,0,0.4);"><?php echo $day['total_records']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($view_mode === 'student'): ?>
                <!-- STUDENT VIEW -->

                <?php if ($selected_student_id == 0): ?>
                    <div class="abs-card p-5 text-center">
                        <i class="bi bi-person-circle" style="font-size: 48px; color: rgba(0,0,0,0.1);"></i>
                        <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Sélectionnez un élève</h3>
                        <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Choisissez un élève dans la barre de filtre ci-dessus.</p>
                    </div>
                <?php else: ?>
                    <!-- Student Stats -->
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-num" style="color: #22c55e;"><?php echo $student_total_present; ?></div>
                                <div class="stat-label">Présences</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-num" style="color: #ef4444;"><?php echo $student_total_absent; ?></div>
                                <div class="stat-label">Absences</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-num" style="color: #f59e0b;"><?php echo $student_total_late; ?></div>
                                <div class="stat-label">Retards</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <div class="stat-num" style="color: #6366f1;"><?php echo $student_total_justified; ?></div>
                                <div class="stat-label">Justifiées</div>
                            </div>
                        </div>
                    </div>

                    <!-- Student Detail -->
                    <div class="abs-card">
                        <div class="p-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="stu-avatar" style="background: rgba(99,102,241,0.1); color: #6366f1;">
                                    <?php echo strtoupper(substr($selected_student_name, 0, 2)); ?>
                                </div>
                                <h3 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">
                                    <?php echo htmlspecialchars($selected_student_name); ?>
                                </h3>
                            </div>
                            <a href="<?php echo buildFilterUrl(['class_id' => $selected_class_id, 'date_from' => $date_from, 'date_to' => $date_to, 'view' => 'group']); ?>" 
                               class="btn btn-sm" style="background: rgba(0,0,0,0.04); border-radius: 8px; font-size: 12px; color: #555; padding: 5px 12px; text-decoration: none;">
                                <i class="bi bi-arrow-left me-1"></i>Retour
                            </a>
                        </div>

                        <?php if (empty($history_data)): ?>
                            <div class="p-5 text-center">
                                <i class="bi bi-calendar-x" style="font-size: 40px; color: rgba(0,0,0,0.1);"></i>
                                <p style="font-size: 14px; color: rgba(0,0,0,0.35); margin: 12px 0 0;">Aucune donnée pour cet élève dans cette période.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table hist-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Jour</th>
                                            <th class="text-center">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history_data as $record): ?>
                                        <?php 
                                            $d = new DateTime($record['absence_date']);
                                            $statut = $record['statut'] ?? 'present';
                                            $status_config = [
                                                'present' => ['Présent', '#22c55e', 'rgba(34,197,94,0.1)'],
                                                'absent' => ['Absent', '#ef4444', 'rgba(239,68,68,0.1)'],
                                                'late' => ['Retard', '#f59e0b', 'rgba(245,158,11,0.1)'],
                                                'justified' => ['Justifié', '#6366f1', 'rgba(99,102,241,0.1)']
                                            ];
                                            $sc = $status_config[$statut] ?? $status_config['present'];
                                        ?>
                                        <tr>
                                            <td style="font-weight: 500;">
                                                <?php echo $d->format('d/m/Y'); ?>
                                                <?php if ($record['absence_date'] === date('Y-m-d')): ?>
                                                    <span style="background:rgba(34,197,94,0.15);color:#22c55e;font-size:10px;padding:1px 6px;border-radius:4px;margin-left:4px;">Auj.</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color: rgba(0,0,0,0.4);"><?php echo $day_names_fr[$d->format('w')]; ?></td>
                                            <td class="text-center">
                                                <span class="status-badge" style="background: <?php echo $sc[2]; ?>; color: <?php echo $sc[1]; ?>;">
                                                    <span class="dot" style="background: <?php echo $sc[1]; ?>;"></span>
                                                    <?php echo $sc[0]; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php elseif (empty($classes)): ?>
                <div class="abs-card p-5 text-center">
                    <i class="bi bi-building" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Aucune classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Créez une classe pour voir l'historique des absences.</p>
                </div>
            <?php else: ?>
                <div class="abs-card p-5 text-center">
                    <i class="bi bi-arrow-up-circle" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Sélectionnez une classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin: 0;">Choisissez une classe ci-dessus.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

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
