<?php
/**
 * Certificates Page — Redesigned
 * Generate and download certificates for students who completed all controles
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';
require_once 'classes/Student.php';
require_once 'classes/Teacher.php';
require_once 'classes/CertificateGenerator.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);
$student = new Student($db);
$teacher = new Teacher($db);

// Get teacher info
if ($teacher->getById($_SESSION['teacher_id'])) {
    $teacher_name = $teacher->nom;
} else {
    $teacher_name = "Teacher";
}

/**
 * Get all required templates for a class based on its year level (Modèles).
 * Returns array of template IDs that must be completed, or empty if class has no year level.
 */
function getRequiredTemplates($db, $class_id, $teacher_id) {
    // Get the class's year_level_id
    $yl_stmt = $db->prepare("SELECT year_level_id FROM classes WHERE id = :cid");
    $yl_stmt->execute([':cid' => $class_id]);
    $class_row = $yl_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$class_row || empty($class_row['year_level_id'])) {
        return []; // No year level = no required templates
    }
    
    // Get ALL active templates for this year level (the Modèles)
    $tpl_stmt = $db->prepare("SELECT id, title, total_points FROM controle_templates 
                               WHERE year_level_id = :ylid AND is_active = 1 AND created_by = :tid
                               ORDER BY title ASC");
    $tpl_stmt->execute([':ylid' => $class_row['year_level_id'], ':tid' => $teacher_id]);
    return $tpl_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check a student's completion status against ALL required templates.
 * A template is "completed" when there is at least one instance for the class
 * with a controle_results entry (final_note >= 0) for that student.
 * Returns: [completed_count, total_templates, total_score, max_possible, completed_template_ids[]]
 */
function checkStudentCompletion($db, $student_id, $class_id, $teacher_id, $required_templates) {
    $completed_count = 0;
    $total_score = 0;
    $max_possible = 0;
    $completed_ids = [];
    
    foreach ($required_templates as $tpl) {
        // Find all instances of this template for this class
        $inst_stmt = $db->prepare("SELECT ci.id FROM controle_instances ci 
                                    WHERE ci.template_id = :tid AND ci.class_id = :cid AND ci.created_by = :teacher_id");
        $inst_stmt->execute([':tid' => $tpl['id'], ':cid' => $class_id, ':teacher_id' => $teacher_id]);
        $instances = $inst_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if at least one instance has a valid result for this student
        $template_done = false;
        foreach ($instances as $inst) {
            $res_stmt = $db->prepare("SELECT final_note FROM controle_results 
                                       WHERE instance_id = :iid AND student_id = :sid AND final_note >= 0");
            $res_stmt->execute([':iid' => $inst['id'], ':sid' => $student_id]);
            $result = $res_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $total_score += $result['final_note'];
                $max_possible += $tpl['total_points'];
                $template_done = true;
                break; // One completed instance per template is enough
            }
        }
        
        if ($template_done) {
            $completed_count++;
            $completed_ids[] = $tpl['id'];
        }
    }
    
    return [$completed_count, count($required_templates), $total_score, $max_possible, $completed_ids];
}

// Handle certificate generation (single or bulk)
$is_bulk = isset($_GET['bulk']) && $_GET['bulk'] === '1';
$generate_single = isset($_GET['generate']) && isset($_GET['student_id']) && isset($_GET['class_id']);

if ($generate_single || $is_bulk) {
    $class_id = intval($_GET['class_id'] ?? 0);

    if ($classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
        $class_name = $classroom->nom;

        // Get ALL required templates (Modèles) for this class's year level
        $required_templates = getRequiredTemplates($db, $class_id, $_SESSION['teacher_id']);
        $total_templates = count($required_templates);

        if ($total_templates == 0) {
            $_SESSION['cert_error'] = "Aucun modèle de contrôle défini pour le niveau de cette classe.";
            header("Location: index.php?page=certificates&class_id=$class_id");
            exit;
        }

        if ($is_bulk) {
            // Bulk download: generate ZIP with PDFs for all eligible students
            $students_stmt = $student->getByClass($class_id);
            $all_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $eligible_for_bulk = [];
            foreach ($all_students as $s) {
                [$completed, $total] = checkStudentCompletion($db, $s['id'], $class_id, $_SESSION['teacher_id'], $required_templates);
                if ($completed == $total) {
                    $eligible_for_bulk[] = $s;
                }
            }

            if (!empty($eligible_for_bulk)) {
                // Generate ZIP with individual PDFs
                $tmpDir = sys_get_temp_dir() . '/certs_' . uniqid();
                mkdir($tmpDir, 0777, true);
                $files = [];

                foreach ($eligible_for_bulk as $es) {
                    $cert = new CertificateGenerator($es['nom'], $class_name, $teacher_name, "Achèvement de tous les contrôles");
                    $filename = "Certificat_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $es['nom']) . ".pdf";
                    $filepath = $tmpDir . '/' . $filename;
                    
                    $cert->generateCertificateToFile($filepath);
                    
                    $files[] = $filepath;
                }

                // Create ZIP
                $zipFile = $tmpDir . '/Certificats_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $class_name) . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                    foreach ($files as $f) {
                        $zip->addFile($f, basename($f));
                    }
                    $zip->close();

                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
                    header('Content-Length: ' . filesize($zipFile));
                    readfile($zipFile);

                    // Cleanup
                    foreach ($files as $f) @unlink($f);
                    @unlink($zipFile);
                    @rmdir($tmpDir);
                    exit;
                }
            } else {
                $_SESSION['cert_error'] = "Aucun élève éligible pour le téléchargement groupé.";
                header("Location: index.php?page=certificates&class_id=$class_id");
                exit;
            }
        } elseif ($generate_single) {
            $student_id = intval($_GET['student_id']);
            $student_query = "SELECT * FROM eleves WHERE id = :student_id";
            $student_stmt = $db->prepare($student_query);
            $student_stmt->execute([':student_id' => $student_id]);
            $student_data = $student_stmt->fetch(PDO::FETCH_ASSOC);

            if ($student_data) {
                [$completed_count, $total_tpl] = checkStudentCompletion($db, $student_id, $class_id, $_SESSION['teacher_id'], $required_templates);

                if ($completed_count == $total_tpl) {
                    $cert = new CertificateGenerator($student_data['nom'], $class_name, $teacher_name, "Achèvement de tous les contrôles");
                    $filename = "Certificat_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $student_data['nom']) . "_" . date('Y-m-d') . ".pdf";
                    $cert->generateCertificate($filename);
                    exit;
                } else {
                    $_SESSION['cert_error'] = "Cet élève n'a pas complété tous les modèles de contrôles ($completed_count/$total_tpl).";
                    header("Location: index.php?page=certificates&class_id=$class_id");
                    exit;
                }
            }
        }
    }
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Selected class — from GET or auto-select first
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
if ($selected_class_id == 0 && !empty($classes)) {
    $selected_class_id = $classes[0]['id'];
}

$eligible_students = [];
$inprogress_students = [];
$selected_class_name = '';
$total_controles = 0;

if ($selected_class_id > 0) {
    // Verify ownership
    $valid = false;
    foreach ($classes as $c) {
        if ($c['id'] == $selected_class_id) {
            $valid = true;
            $selected_class_name = $c['nom'];
            break;
        }
    }

    if ($valid) {
        // Get ALL required templates (Modèles) for this class's year level
        $required_templates = getRequiredTemplates($db, $selected_class_id, $_SESSION['teacher_id']);
        $total_controles = count($required_templates);
        
        // Also get which templates have instances created for this class
        $inst_check = $db->prepare("SELECT DISTINCT ci.template_id 
                                     FROM controle_instances ci 
                                     WHERE ci.class_id = :cid AND ci.created_by = :tid");
        $inst_check->execute([':cid' => $selected_class_id, ':tid' => $_SESSION['teacher_id']]);
        $instantiated_templates = array_column($inst_check->fetchAll(PDO::FETCH_ASSOC), 'template_id');
        
        // Find templates not yet instantiated (missing controles)
        $missing_templates = [];
        foreach ($required_templates as $tpl) {
            if (!in_array($tpl['id'], $instantiated_templates)) {
                $missing_templates[] = $tpl;
            }
        }

        if ($total_controles > 0) {
            $students_stmt = $student->getByClass($selected_class_id);
            $students_all = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($students_all as $s) {
                [$completed_count, $total_tpl, $total_score, $max_possible] = checkStudentCompletion(
                    $db, $s['id'], $selected_class_id, $_SESSION['teacher_id'], $required_templates
                );

                $average = $max_possible > 0 ? round(($total_score / $max_possible) * 20, 2) : 0;
                $data = [
                    'id' => $s['id'],
                    'nom' => $s['nom'],
                    'completed' => $completed_count,
                    'total' => $total_controles,
                    'average' => $average,
                    'total_score' => $total_score,
                    'max_possible' => $max_possible,
                    'progress_percent' => $total_controles > 0 ? round(($completed_count / $total_controles) * 100) : 0
                ];

                if ($completed_count == $total_controles) {
                    $eligible_students[] = $data;
                } else {
                    $inprogress_students[] = $data;
                }
            }
        }
    } else {
        $selected_class_id = 0;
    }
}

// Sort: eligible by average desc, in-progress by progress desc
usort($eligible_students, fn($a, $b) => $b['average'] <=> $a['average']);
usort($inprogress_students, fn($a, $b) => $b['progress_percent'] <=> $a['progress_percent']);

$total_students = count($eligible_students) + count($inprogress_students);
$eligible_count = count($eligible_students);
$eligible_percent = $total_students > 0 ? round(($eligible_count / $total_students) * 100) : 0;
$avg_eligible = $eligible_count > 0 ? round(array_sum(array_column($eligible_students, 'average')) / $eligible_count, 2) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Certificats - No9ati</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon-72x72.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background: #f8f9fb; }

        .cert-card {
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

        /* Stats */
        .stat-box {
            border-radius: 12px; padding: 16px; text-align: center;
            border: 1px solid rgba(0,0,0,0.05); background: #fff;
            transition: transform 0.15s ease;
        }
        .stat-box:hover { transform: translateY(-2px); }
        .stat-box .stat-num { font-size: 26px; font-weight: 700; line-height: 1; }
        .stat-box .stat-label { font-size: 12px; color: rgba(0,0,0,0.45); margin-top: 4px; font-weight: 500; }

        /* Student row */
        .stu-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; gap: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            transition: background 0.1s ease;
        }
        .stu-row:last-child { border-bottom: none; }
        .stu-row:hover { background: rgba(0,0,0,0.01); }

        .stu-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
        }

        /* Progress bar */
        .prog-bar {
            height: 6px; border-radius: 3px; background: rgba(0,0,0,0.06);
            overflow: hidden; min-width: 60px;
        }
        .prog-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s ease; }

        /* Download button */
        .dl-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
            border: none; cursor: pointer; transition: all 0.15s ease;
            text-decoration: none; white-space: nowrap;
        }
        .dl-btn-primary { background: #1c1c1c; color: #fff; }
        .dl-btn-primary:hover { background: #333; color: #fff; }
        .dl-btn-success { background: #22c55e; color: #fff; }
        .dl-btn-success:hover { background: #16a34a; color: #fff; }

        /* Table */
        .cert-table th {
            font-size: 11px; font-weight: 600; color: rgba(0,0,0,0.4);
            text-transform: uppercase; letter-spacing: 0.4px;
            padding: 10px 14px; border-bottom: 2px solid rgba(0,0,0,0.06);
        }
        .cert-table td {
            padding: 0; border-bottom: none; vertical-align: middle;
        }

        /* Toast */
        .cert-toast {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            padding: 14px 22px; border-radius: 12px; display: flex; align-items: center; gap: 10px;
            font-size: 14px; font-weight: 500; color: #fff;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            animation: slideInToast 0.3s ease;
        }
        .cert-toast.error { background: #ef4444; }
        @keyframes slideInToast {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Average badge */
        .avg-badge {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 3px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600;
        }

        @media (max-width: 768px) {
            .stu-row { flex-wrap: wrap; gap: 8px; }
            .stu-actions { width: 100%; justify-content: flex-end; }
            .hide-mobile { display: none !important; }
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
                    <h1 style="font-size: 15px; font-weight: 600; color: #1c1c1c; margin: 0; line-height: 1.2;">Certificats</h1>
                    <p style="font-size: 12px; color: rgba(0,0,0,0.4); margin: 0;">Certificats basés sur <?php echo $total_controles; ?> modèle(s) défini(s)</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: #1c1c1c; color: #fff; border-radius: 50%; font-size: 13px; font-weight: 500;">
                        <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
            </div>
        </nav>

        <main class="container-fluid" style="padding: 20px 24px;">
            <!-- Error toast -->
            <?php if (isset($_SESSION['cert_error'])): ?>
            <div class="cert-toast error" id="certToast">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?php echo htmlspecialchars($_SESSION['cert_error']); unset($_SESSION['cert_error']); ?></span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #fff; opacity: 0.7; cursor: pointer; margin-left: 8px;"><i class="bi bi-x-lg"></i></button>
            </div>
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
                                <a href="index.php?page=certificates&class_id=<?php echo $c['id']; ?>"
                                   class="sd-opt <?php echo $selected_class_id == $c['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($c['nom']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($classes)): ?>
                <div class="cert-card p-5 text-center">
                    <i class="bi bi-building" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Aucune classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin-bottom: 16px;">Créez une classe pour commencer.</p>
                    <a href="index.php?page=manage_classes" class="dl-btn dl-btn-primary" style="padding: 8px 20px; font-size: 14px; border-radius: 10px;">
                        <i class="bi bi-plus-lg"></i> Créer une classe
                    </a>
                </div>
            <?php elseif ($selected_class_id == 0): ?>
                <div class="cert-card p-5 text-center">
                    <i class="bi bi-arrow-up-circle" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Sélectionnez une classe</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4);">Choisissez une classe ci-dessus.</p>
                </div>
            <?php elseif ($total_controles == 0): ?>
                <div class="cert-card p-5 text-center">
                    <i class="bi bi-journal-x" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Aucun modèle de contrôle</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4); margin-bottom: 16px;">
                        <?php
                        // Check if class has a year level
                        $yl_stmt = $db->prepare("SELECT year_level_id FROM classes WHERE id = :cid");
                        $yl_stmt->execute([':cid' => $selected_class_id]);
                        $yl_row = $yl_stmt->fetch(PDO::FETCH_ASSOC);
                        if (empty($yl_row['year_level_id'])):
                        ?>
                            Cette classe n'a pas de niveau d'année associé. Assignez un niveau pour activer les modèles de contrôle.
                        <?php else: ?>
                            Aucun modèle de contrôle (Modèle) défini pour le niveau de cette classe. Créez des modèles dans les contrôles.
                        <?php endif; ?>
                    </p>
                    <a href="index.php?page=controles" class="dl-btn dl-btn-primary" style="padding: 8px 20px; font-size: 14px; border-radius: 10px;">
                        <i class="bi bi-plus-lg"></i> Gérer les contrôles
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($missing_templates)): ?>
                <!-- Warning: some templates not yet instantiated -->
                <div class="cert-card mb-3 p-3 d-flex align-items-start gap-3" style="border-left: 3px solid #f59e0b; background: rgba(245,158,11,0.03);">
                    <i class="bi bi-exclamation-triangle-fill" style="color: #f59e0b; font-size: 18px; flex-shrink: 0; margin-top: 2px;"></i>
                    <div>
                        <p style="font-size: 13px; font-weight: 600; color: #1c1c1c; margin: 0 0 4px;">
                            <?php echo count($missing_templates); ?> modèle(s) non encore évalué(s)
                        </p>
                        <p style="font-size: 12px; color: rgba(0,0,0,0.5); margin: 0;">
                            Les modèles suivants n'ont pas encore d'évaluation créée pour cette classe :
                            <?php echo implode(', ', array_map(fn($t) => '<strong>' . htmlspecialchars($t['title']) . '</strong>', $missing_templates)); ?>.
                            Aucun élève ne sera éligible au certificat tant que tous les modèles ne sont pas évalués.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #1c1c1c;"><?php echo $total_students; ?></div>
                            <div class="stat-label">Total élèves</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #22c55e;"><?php echo $eligible_count; ?></div>
                            <div class="stat-label">Éligibles (<?php echo $eligible_percent; ?>%)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #f59e0b;"><?php echo count($inprogress_students); ?></div>
                            <div class="stat-label">En cours</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-num" style="color: #6366f1;"><?php echo $avg_eligible > 0 ? $avg_eligible : '—'; ?></div>
                            <div class="stat-label">Moyenne / 20</div>
                        </div>
                    </div>
                </div>

                <!-- Eligible Students -->
                <?php if (!empty($eligible_students)): ?>
                <div class="cert-card mb-3">
                    <div class="p-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <div class="d-flex align-items-center gap-2">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e;"></span>
                            <h3 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">
                                Éligibles
                            </h3>
                            <span style="font-size: 12px; color: rgba(0,0,0,0.35); font-weight: 500;"><?php echo $eligible_count; ?> élève(s)</span>
                        </div>
                        <?php if ($eligible_count > 1): ?>
                        <a href="index.php?page=certificates&bulk=1&class_id=<?php echo $selected_class_id; ?>" 
                           class="dl-btn dl-btn-success" title="Télécharger tous les certificats en ZIP">
                            <i class="bi bi-download"></i> Tout télécharger
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php foreach ($eligible_students as $idx => $es): ?>
                    <div class="stu-row">
                        <div class="d-flex align-items-center gap-3" style="min-width: 0; flex: 1;">
                            <span style="font-size: 12px; color: rgba(0,0,0,0.2); font-weight: 600; width: 20px; text-align: center; flex-shrink: 0;"><?php echo $idx + 1; ?></span>
                            <div class="stu-avatar" style="background: rgba(34,197,94,0.1); color: #22c55e;">
                                <?php echo strtoupper(substr($es['nom'], 0, 2)); ?>
                            </div>
                            <div style="min-width: 0; flex: 1;">
                                <p style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($es['nom']); ?>
                                </p>
                                <p style="font-size: 11px; color: rgba(0,0,0,0.35); margin: 0;">
                                    <?php echo $es['completed']; ?>/<?php echo $es['total']; ?> modèle(s) &middot;
                                    <?php echo $es['total_score']; ?>/<?php echo $es['max_possible']; ?> pts
                                </p>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 stu-actions hide-mobile">
                            <span class="avg-badge" style="background: <?php echo $es['average'] >= 10 ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)'; ?>; color: <?php echo $es['average'] >= 10 ? '#22c55e' : '#ef4444'; ?>;">
                                <?php echo $es['average']; ?>/20
                            </span>
                        </div>

                        <a href="index.php?page=certificates&generate=1&student_id=<?php echo $es['id']; ?>&class_id=<?php echo $selected_class_id; ?>" 
                           class="dl-btn dl-btn-primary">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                    </div>
                    <?php endforeach; ?>

                    <div class="p-3" style="border-top: 1px solid rgba(0,0,0,0.04);">
                        <p style="font-size: 12px; color: rgba(0,0,0,0.35); margin: 0;">
                            <i class="bi bi-info-circle me-1"></i>
                            Éligible = tous les <?php echo $total_controles; ?> modèle(s) complétés. Classe «&nbsp;<?php echo htmlspecialchars($selected_class_name); ?>&nbsp;».
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- In-Progress Students -->
                <?php if (!empty($inprogress_students)): ?>
                <div class="cert-card">
                    <div class="p-3 d-flex align-items-center gap-2" style="border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
                        <h3 style="font-size: 14px; font-weight: 600; color: #1c1c1c; margin: 0;">
                            En cours
                        </h3>
                        <span style="font-size: 12px; color: rgba(0,0,0,0.35); font-weight: 500;"><?php echo count($inprogress_students); ?> élève(s)</span>
                    </div>

                    <?php foreach ($inprogress_students as $idx => $ps): ?>
                    <?php
                        $prog_color = $ps['progress_percent'] >= 75 ? '#22c55e' : ($ps['progress_percent'] >= 50 ? '#f59e0b' : '#ef4444');
                        $remaining = $ps['total'] - $ps['completed'];
                    ?>
                    <div class="stu-row" style="opacity: 0.7;">
                        <div class="d-flex align-items-center gap-3" style="min-width: 0; flex: 1;">
                            <span style="font-size: 12px; color: rgba(0,0,0,0.2); font-weight: 600; width: 20px; text-align: center; flex-shrink: 0;"><?php echo $idx + 1; ?></span>
                            <div class="stu-avatar" style="background: rgba(245,158,11,0.1); color: #f59e0b;">
                                <?php echo strtoupper(substr($ps['nom'], 0, 2)); ?>
                            </div>
                            <div style="min-width: 0; flex: 1;">
                                <p style="font-size: 14px; font-weight: 500; color: #1c1c1c; margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($ps['nom']); ?>
                                </p>
                                <p style="font-size: 11px; color: rgba(0,0,0,0.35); margin: 0;">
                                    <?php echo $ps['completed']; ?>/<?php echo $ps['total']; ?> modèle(s) &middot;
                                    <?php echo $remaining; ?> manquant(s)
                                </p>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <div class="prog-bar" style="width: 60px;">
                                <div class="prog-bar-fill" style="width: <?php echo $ps['progress_percent']; ?>%; background: <?php echo $prog_color; ?>;"></div>
                            </div>
                            <span style="font-size: 11px; font-weight: 600; color: <?php echo $prog_color; ?>; min-width: 30px;"><?php echo $ps['progress_percent']; ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="p-3" style="border-top: 1px solid rgba(0,0,0,0.04);">
                        <p style="font-size: 12px; color: rgba(0,0,0,0.35); margin: 0;">
                            <i class="bi bi-clock me-1"></i>
                            Ces élèves deviendront éligibles une fois tous les <?php echo $total_controles; ?> modèle(s) notés.
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($eligible_students) && empty($inprogress_students)): ?>
                <div class="cert-card p-5 text-center">
                    <i class="bi bi-people" style="font-size: 48px; color: rgba(0,0,0,0.12);"></i>
                    <h3 style="font-size: 17px; font-weight: 600; color: #1c1c1c; margin: 16px 0 6px;">Aucun élève</h3>
                    <p style="font-size: 14px; color: rgba(0,0,0,0.4);">Aucun élève inscrit dans cette classe.</p>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>
    // Auto-dismiss error toast
    const toast = document.getElementById('certToast');
    if (toast) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-12px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

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
                emptyEl.textContent = 'Aucun r\u00e9sultat';
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
