<?php
/**
 * Unified Contrôles Hub
 * Two tabs: Modèles (templates) and Évaluations (instances)
 * Clean, long-term manageable design
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);

$message = '';
$error = '';

// Active tab — Évaluations is the primary/default tab
$active_tab = $_GET['tab'] ?? 'evaluations';

// Get year levels
$year_levels = $controle->getYearLevels($_SESSION['teacher_id']);
$year_lookup = [];
foreach ($year_levels as $yl) {
    $year_lookup[$yl['id']] = $yl['name'];
}

// Get teacher's classes
$classes_stmt = $classroom->getByTeacher($_SESSION['teacher_id']);
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// TEMPLATE ACTIONS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_template'])) {
    $title = trim($_POST['title'] ?? '');
    $year_level_id = intval($_POST['year_level_id'] ?? 0);
    $total_points = floatval($_POST['total_points'] ?? 20);
    $description = trim($_POST['description'] ?? '');
    
    if (!empty($title) && $year_level_id > 0) {
        $template_id = $controle->createTemplate($title, $year_level_id, $total_points, $description, $_SESSION['teacher_id']);
        if ($template_id) {
            header("Location: index.php?page=controle_template_edit&id=" . $template_id);
            exit;
        } else {
            $error = "Erreur lors de la création du modèle.";
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_template'])) {
    $template_id = intval($_POST['template_id'] ?? 0);
    if ($template_id > 0) {
        $template = $controle->getTemplateById($template_id);
        if ($template && $template['created_by'] == $_SESSION['teacher_id']) {
            if ($controle->deleteTemplate($template_id)) {
                $message = "Modèle supprimé avec succès.";
            } else {
                $error = "Impossible de supprimer : ce modèle est utilisé par des évaluations.";
            }
        }
    }
    $active_tab = 'templates';
}

// ============================================================================
// INSTANCE ACTIONS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_instance'])) {
    $template_id = intval($_POST['template_id'] ?? 0);
    $class_id = intval($_POST['class_id'] ?? 0);
    $session_name = trim($_POST['session_name'] ?? '');
    
    if ($template_id > 0 && $class_id > 0) {
        if ($classroom->getById($class_id) && $classroom->enseignant_id == $_SESSION['teacher_id']) {
            $instance_id = $controle->createInstance($template_id, $class_id, $_SESSION['teacher_id'], $session_name);
            if ($instance_id) {
                header("Location: index.php?page=controle_fill_notes&id=" . $instance_id);
                exit;
            } else {
                $error = "Erreur : Le niveau de la classe doit correspondre au niveau du modèle.";
            }
        } else {
            $error = "Classe non autorisée.";
        }
    } else {
        $error = "Veuillez sélectionner un modèle et une classe.";
    }
    $active_tab = 'evaluations';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_instance'])) {
    $instance_id = intval($_POST['instance_id'] ?? 0);
    if ($instance_id > 0) {
        $instance = $controle->getInstanceById($instance_id);
        if ($instance && $instance['created_by'] == $_SESSION['teacher_id'] && !$instance['is_locked']) {
            if ($controle->deleteInstance($instance_id)) {
                $message = "Évaluation supprimée.";
            } else {
                $error = "Erreur lors de la suppression.";
            }
        } else if ($instance && $instance['is_locked']) {
            $error = "Impossible de supprimer une évaluation verrouillée.";
        }
    }
    $active_tab = 'evaluations';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_lock'])) {
    $instance_id = intval($_POST['instance_id'] ?? 0);
    if ($instance_id > 0) {
        $instance = $controle->getInstanceById($instance_id);
        if ($instance && $instance['created_by'] == $_SESSION['teacher_id']) {
            if ($instance['is_locked']) {
                $controle->unlockInstance($instance_id);
                $message = "Évaluation déverrouillée.";
            } else {
                $controle->lockInstance($instance_id);
                $message = "Évaluation verrouillée.";
            }
        }
    }
    $active_tab = 'evaluations';
}

// ============================================================================
// FETCH DATA
// ============================================================================

// Templates
$filter_year_id = isset($_GET['year_id']) ? intval($_GET['year_id']) : 0;
if ($filter_year_id > 0) {
    $templates = $controle->getTemplatesByYearLevel($filter_year_id);
} else {
    $templates = $controle->getTemplatesByTeacher($_SESSION['teacher_id']);
}

$templates_by_year = [];
foreach ($templates as $t) {
    $year_key = $t['year_level_id'] ?? 0;
    $templates_by_year[$year_key][] = $t;
}

// Instances
$filter_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
if ($filter_class > 0) {
    $instances = $controle->getInstancesByClass($filter_class);
} else {
    $instances = $controle->getInstancesByTeacher($_SESSION['teacher_id']);
}

// ============================================================================
// RÉSULTATS TAB DATA
// ============================================================================
$results_data = [];
$results_filter_class = isset($_GET['res_class']) ? intval($_GET['res_class']) : 0;

if ($active_tab === 'resultats') {
    // Determine which class to show results for
    $res_class_id = $results_filter_class;

    // If no class selected, pick the first class
    if ($res_class_id == 0 && !empty($classes)) {
        $res_class_id = $classes[0]['id'];
        $results_filter_class = $res_class_id;
    }

    if ($res_class_id > 0) {
        // Get all instances for this class
        $class_instances = $controle->getInstancesByClass($res_class_id);

        // Get students of this class
        require_once 'classes/Student.php';
        $student_obj = new Student($db);
        $students_stmt = $student_obj->getByClass($res_class_id);
        $class_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build results grid: students × instances
        foreach ($class_students as &$stu) {
            $stu['results'] = [];
            $stu['total_score'] = 0;
            $stu['total_max'] = 0;
            $stu['graded_count'] = 0;
            foreach ($class_instances as $ci) {
                $result = $controle->getStudentResult($ci['id'], $stu['id']);
                if ($result && $result['final_note'] >= 0) {
                    $stu['results'][$ci['id']] = $result['final_note'];
                    $stu['total_score'] += $result['final_note'];
                    $stu['total_max'] += $ci['total_points'];
                    $stu['graded_count']++;
                } else {
                    $stu['results'][$ci['id']] = null;
                }
            }
            $stu['average'] = $stu['graded_count'] > 0
                ? round(($stu['total_score'] / $stu['total_max']) * 20, 2) 
                : null;
        }
        unset($stu);

        // Sort by average descending
        usort($class_students, function($a, $b) {
            if ($a['average'] === null && $b['average'] === null) return 0;
            if ($a['average'] === null) return 1;
            if ($b['average'] === null) return -1;
            return $b['average'] <=> $a['average'];
        });

        $results_data = [
            'students' => $class_students,
            'instances' => $class_instances,
            'class_id' => $res_class_id,
        ];
    }
}

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<style>
.c-hub .tab-btn {
    padding: 10px 24px;
    border: none;
    background: none;
    font-size: 14px;
    font-weight: 500;
    color: rgba(0,0,0,0.45);
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.c-hub .tab-btn:hover { color: #1c1c1c; }
.c-hub .tab-btn.active {
    color: #1c1c1c;
    font-weight: 600;
    border-bottom-color: #1c1c1c;
}
.c-hub .c-card {
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 16px;
    padding: 20px;
    background: #fff;
    transition: all 0.18s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.c-hub .c-card:hover {
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
    border-color: rgba(0,0,0,0.1);
    transform: translateY(-1px);
}
.c-hub .dot {
    width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex-shrink: 0;
}
.c-hub .dot-ok { background: #22c55e; }
.c-hub .dot-warn { background: #f59e0b; }
.c-hub .dot-lock { background: #94a3b8; }
.c-hub .year-divider {
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 1.5px; color: rgba(0,0,0,0.3); padding: 4px 0; margin-bottom: 12px;
}
.c-hub .chip {
    padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 500;
    border: 1px solid rgba(0,0,0,0.1); background: #fff; color: #555;
    text-decoration: none; transition: all 0.15s; display: inline-block;
}
.c-hub .chip:hover { border-color: rgba(0,0,0,0.25); color: #1c1c1c; }
.c-hub .chip.on { background: #1c1c1c; color: #fff; border-color: #1c1c1c; }
.c-hub .empty-box {
    padding: 56px 20px; text-align: center;
    border: 2px dashed rgba(0,0,0,0.07); border-radius: 16px; background: rgba(0,0,0,0.01);
}
.c-hub .tag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;
    background: rgba(0,0,0,0.04); color: rgba(0,0,0,0.55);
}
.c-hub .bar { height: 4px; border-radius: 2px; background: rgba(0,0,0,0.06); overflow: hidden; }
.c-hub .bar-fill { height: 100%; border-radius: 2px; transition: width 0.3s; }
.c-hub .cnt { font-size: 11px; font-weight: 600; background: rgba(0,0,0,0.06); color: rgba(0,0,0,0.55); padding: 2px 8px; border-radius: 10px; }
.c-hub .res-table th, .c-hub .res-table td { border-color: rgba(0,0,0,0.04); }
.c-hub .res-table tbody tr:hover { background: rgba(0,0,0,0.015); }

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

@media (max-width: 768px) {
    .c-hub .tab-btn { padding: 10px 14px; font-size: 13px; }
    .sd-wrap { max-width: 100%; }
}
</style>

<main class="main-content" style="padding-top: 88px;">
<div class="container-fluid c-hub" style="padding: 24px 28px;">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h4 class="mb-1" style="font-weight: 700; font-size: 22px;">Contrôles</h4>
            <p class="text-muted mb-0" style="font-size: 13px;">Gérez les modèles et évaluations de vos classes</p>
        </div>
        <?php if ($active_tab === 'templates'): ?>
        <button type="button" class="btn btn-dark d-flex align-items-center gap-2 rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createTemplateModal" style="font-size: 14px;">
            <i class="bi bi-plus-lg"></i> Nouveau modèle
        </button>
        <?php elseif ($active_tab === 'evaluations'): ?>
        <button type="button" class="btn btn-dark d-flex align-items-center gap-2 rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createInstanceModal" style="font-size: 14px;">
            <i class="bi bi-plus-lg"></i> Nouvelle évaluation
        </button>
        <?php endif; ?>
    </div>

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 14px;" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size: 10px;"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 14px;" role="alert">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size: 10px;"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="d-flex border-bottom mb-4" style="gap: 4px;">
        <a href="index.php?page=controles&tab=evaluations" class="tab-btn text-decoration-none <?php echo $active_tab === 'evaluations' ? 'active' : ''; ?>">
            <i class="bi bi-journal-check"></i> Évaluations <span class="cnt"><?php echo count($instances); ?></span>
        </a>
        <a href="index.php?page=controles&tab=resultats" class="tab-btn text-decoration-none <?php echo $active_tab === 'resultats' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-line"></i> Résultats
        </a>
        <a href="index.php?page=controles&tab=templates" class="tab-btn text-decoration-none <?php echo $active_tab === 'templates' ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-ruled"></i> Modèles <span class="cnt"><?php echo count($templates); ?></span>
        </a>
    </div>

    <!-- ====================================================================== -->
    <!-- TEMPLATES TAB -->
    <!-- ====================================================================== -->
    <?php if ($active_tab === 'templates'): ?>

        <?php if (!empty($year_levels)): ?>
        <div class="mb-4">
            <div class="sd-wrap" id="sdYearFilter">
                <button type="button" class="sd-toggle" onclick="sdToggle('sdYearFilter')">
                    <i class="bi bi-funnel" style="color: rgba(0,0,0,0.35);"></i>
                    <span class="sd-label"><?php echo $filter_year_id > 0 ? htmlspecialchars($year_lookup[$filter_year_id] ?? 'Filtrer') : 'Tous les niveaux'; ?></span>
                    <span class="sd-icon">&#9662;</span>
                </button>
                <div class="sd-panel">
                    <input type="text" class="sd-search" placeholder="Rechercher un niveau..." oninput="sdFilter(this)">
                    <div class="sd-list">
                        <a href="index.php?page=controles&tab=templates" class="sd-opt <?php echo $filter_year_id == 0 ? 'active' : ''; ?>">Tous les niveaux</a>
                        <?php foreach ($year_levels as $yl): ?>
                        <a href="index.php?page=controles&tab=templates&year_id=<?php echo $yl['id']; ?>" 
                           class="sd-opt <?php echo $filter_year_id == $yl['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($yl['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($templates)): ?>
            <div class="empty-box">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.13)" stroke-width="1.5" class="mb-3">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <h6 style="font-weight: 600; color: rgba(0,0,0,0.55);">Aucun modèle de contrôle</h6>
                <p class="text-muted mb-3" style="font-size: 13px;">Créez un modèle pour définir la structure de vos évaluations.</p>
                <button type="button" class="btn btn-dark btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                    <i class="bi bi-plus-lg me-1"></i> Créer un modèle
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($templates_by_year as $year_id => $year_templates): ?>
                <div class="year-divider">
                    <i class="bi bi-mortarboard me-1"></i>
                    <?php echo htmlspecialchars($year_lookup[$year_id] ?? "Non classé"); ?>
                </div>
                <div class="row g-3 mb-4">
                    <?php foreach ($year_templates as $t): 
                        $pts_valid = abs(($t['total_phase_points'] ?? 0) - $t['total_points']) < 0.01;
                        $pts_pct = $t['total_points'] > 0 ? min(100, (($t['total_phase_points'] ?? 0) / $t['total_points']) * 100) : 0;
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="c-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div style="min-width: 0; flex: 1;">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="dot <?php echo $pts_valid ? 'dot-ok' : 'dot-warn'; ?>"></span>
                                        <h6 class="mb-0" style="font-weight: 600; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($t['title']); ?>
                                        </h6>
                                    </div>
                                    <?php if ($t['description']): ?>
                                        <p class="text-muted mb-0 ms-3" style="font-size: 12px; line-height: 1.4;"><?php echo htmlspecialchars($t['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="dropdown ms-2 flex-shrink-0">
                                    <button class="btn btn-sm border-0 p-1" type="button" data-bs-toggle="dropdown" style="color: rgba(0,0,0,0.3);"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 13px; border-radius: 12px; min-width: 150px;">
                                        <li><a href="index.php?page=controle_template_edit&id=<?php echo $t['id']; ?>&duplicate=1" class="dropdown-item py-2"><i class="bi bi-copy me-2 opacity-50"></i>Dupliquer</a></li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <form method="POST" onsubmit="return confirm('Supprimer ce modèle ?');">
                                                <input type="hidden" name="template_id" value="<?php echo $t['id']; ?>">
                                                <button type="submit" name="delete_template" class="dropdown-item py-2 text-danger"><i class="bi bi-trash me-2 opacity-50"></i>Supprimer</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="tag"><i class="bi bi-list-task"></i> <?php echo $t['phase_count'] ?? 0; ?> phase<?php echo ($t['phase_count'] ?? 0) > 1 ? 's' : ''; ?></span>
                                <span class="tag"><?php echo $t['total_points']; ?> pts</span>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1" style="font-size: 11px; color: rgba(0,0,0,0.4);">
                                    <span>Points alloués</span>
                                    <span class="<?php echo $pts_valid ? 'text-success' : ''; ?>" style="font-weight: 600;">
                                        <?php echo ($t['total_phase_points'] ?? 0); ?>/<?php echo $t['total_points']; ?>
                                        <?php if ($pts_valid): ?><i class="bi bi-check-circle-fill ms-1"></i><?php endif; ?>
                                    </span>
                                </div>
                                <div class="bar">
                                    <div class="bar-fill" style="width: <?php echo $pts_pct; ?>%; background: <?php echo $pts_valid ? '#22c55e' : '#f59e0b'; ?>;"></div>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <a href="index.php?page=controle_template_edit&id=<?php echo $t['id']; ?>" 
                                   class="btn btn-outline-dark btn-sm w-100 rounded-pill" style="font-size: 13px;">
                                    <i class="bi bi-pencil me-1"></i> Modifier les phases
                                </a>
                            </div>
                            <div class="mt-2 text-center">
                                <small class="text-muted" style="font-size: 11px;">Créé le <?php echo date('d/m/Y', strtotime($t['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ====================================================================== -->
    <!-- EVALUATIONS TAB -->
    <!-- ====================================================================== -->
    <?php if ($active_tab === 'evaluations'): ?>

        <?php if (!empty($classes)): ?>
        <div class="mb-4">
            <div class="sd-wrap" id="sdEvalFilter">
                <button type="button" class="sd-toggle" onclick="sdToggle('sdEvalFilter')">
                    <i class="bi bi-funnel" style="color: rgba(0,0,0,0.35);"></i>
                    <span class="sd-label"><?php
                        if ($filter_class > 0) {
                            foreach ($classes as $c) { if ($c['id'] == $filter_class) { echo htmlspecialchars($c['nom']); break; } }
                        } else { echo 'Toutes les classes'; }
                    ?></span>
                    <span class="sd-icon">&#9662;</span>
                </button>
                <div class="sd-panel">
                    <input type="text" class="sd-search" placeholder="Rechercher une classe..." oninput="sdFilter(this)">
                    <div class="sd-list">
                        <a href="index.php?page=controles&tab=evaluations" class="sd-opt <?php echo $filter_class == 0 ? 'active' : ''; ?>">Toutes les classes</a>
                        <?php foreach ($classes as $cls): ?>
                        <a href="index.php?page=controles&tab=evaluations&class_id=<?php echo $cls['id']; ?>" 
                           class="sd-opt <?php echo $filter_class == $cls['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cls['nom']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($instances)): ?>
            <div class="empty-box">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.13)" stroke-width="1.5" class="mb-3">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <h6 style="font-weight: 600; color: rgba(0,0,0,0.55);">Aucune évaluation en cours</h6>
                <p class="text-muted mb-3" style="font-size: 13px;">Appliquez un modèle à une classe pour commencer la notation.</p>
                <button type="button" class="btn btn-dark btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createInstanceModal">
                    <i class="bi bi-plus-lg me-1"></i> Nouvelle évaluation
                </button>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($instances as $inst): 
                    $progress = $controle->getGradingProgress($inst['id']);
                    $pct = $progress['overall_percentage'] ?? 0;
                    $done = $pct == 100;
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="c-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div style="min-width: 0; flex: 1;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="dot <?php echo $inst['is_locked'] ? 'dot-lock' : ($done ? 'dot-ok' : 'dot-warn'); ?>"></span>
                                    <h6 class="mb-0" style="font-weight: 600; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($inst['template_title']); ?>
                                    </h6>
                                </div>
                                <p class="text-muted mb-0 ms-3" style="font-size: 12px;">
                                    <?php echo htmlspecialchars($inst['class_name']); ?>
                                    <?php if (!empty($inst['session_name'])): ?> · <?php echo htmlspecialchars($inst['session_name']); ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="dropdown ms-2 flex-shrink-0">
                                <button class="btn btn-sm border-0 p-1" type="button" data-bs-toggle="dropdown" style="color: rgba(0,0,0,0.3);"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 13px; border-radius: 12px; min-width: 170px;">
                                    <li><a href="index.php?page=controle_instance_results&id=<?php echo $inst['id']; ?>" class="dropdown-item py-2"><i class="bi bi-graph-up me-2 opacity-50"></i>Résultats</a></li>
                                    <li>
                                        <form method="POST"><input type="hidden" name="instance_id" value="<?php echo $inst['id']; ?>">
                                            <button type="submit" name="toggle_lock" class="dropdown-item py-2">
                                                <i class="bi bi-<?php echo $inst['is_locked'] ? 'unlock' : 'lock'; ?> me-2 opacity-50"></i>
                                                <?php echo $inst['is_locked'] ? 'Déverrouiller' : 'Verrouiller'; ?>
                                            </button>
                                        </form>
                                    </li>
                                    <?php if (!$inst['is_locked']): ?>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Supprimer cette évaluation ?');">
                                            <input type="hidden" name="instance_id" value="<?php echo $inst['id']; ?>">
                                            <button type="submit" name="delete_instance" class="dropdown-item py-2 text-danger"><i class="bi bi-trash me-2 opacity-50"></i>Supprimer</button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size: 11px; color: rgba(0,0,0,0.4);">
                                <span><?php echo $progress['total_filled'] ?? 0; ?>/<?php echo $progress['total_expected'] ?? 0; ?> notes</span>
                                <span style="font-weight: 600;" class="<?php echo $done ? 'text-success' : ''; ?>">
                                    <?php echo $pct; ?>%<?php if ($done): ?> <i class="bi bi-check-circle-fill ms-1"></i><?php endif; ?>
                                </span>
                            </div>
                            <div class="bar"><div class="bar-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $done ? '#22c55e' : '#3b82f6'; ?>;"></div></div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="tag"><?php echo $inst['total_points']; ?> pts</span>
                            <?php if ($inst['is_locked']): ?><span class="tag" style="background: rgba(0,0,0,0.06);"><i class="bi bi-lock-fill"></i> Verrouillé</span><?php endif; ?>
                        </div>

                        <div class="mt-auto d-flex gap-2">
                            <a href="index.php?page=controle_fill_notes&id=<?php echo $inst['id']; ?>" 
                               class="btn btn-dark btn-sm flex-grow-1 rounded-pill" style="font-size: 13px;">
                                <i class="bi bi-pencil-square me-1"></i> Saisir
                            </a>
                            <a href="index.php?page=controle_instance_results&id=<?php echo $inst['id']; ?>" 
                               class="btn btn-outline-dark btn-sm flex-grow-1 rounded-pill" style="font-size: 13px;">
                                <i class="bi bi-bar-chart me-1"></i> Résultats
                            </a>
                        </div>
                        <div class="mt-2 text-center">
                            <small class="text-muted" style="font-size: 11px;"><?php echo date('d/m/Y', strtotime($inst['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ====================================================================== -->
    <!-- RÉSULTATS TAB — Overview of all student notes per class                -->
    <!-- ====================================================================== -->
    <?php if ($active_tab === 'resultats'): ?>

        <!-- Class filter dropdown -->
        <?php if (!empty($classes)): ?>
        <div class="mb-4">
            <div class="sd-wrap" id="sdResFilter">
                <button type="button" class="sd-toggle" onclick="sdToggle('sdResFilter')">
                    <i class="bi bi-funnel" style="color: rgba(0,0,0,0.35);"></i>
                    <span class="sd-label"><?php
                        if ($results_filter_class > 0) {
                            foreach ($classes as $c) { if ($c['id'] == $results_filter_class) { echo htmlspecialchars($c['nom']); break; } }
                        } else { echo 'Sélectionner une classe'; }
                    ?></span>
                    <span class="sd-icon">&#9662;</span>
                </button>
                <div class="sd-panel">
                    <input type="text" class="sd-search" placeholder="Rechercher une classe..." oninput="sdFilter(this)">
                    <div class="sd-list">
                        <?php foreach ($classes as $cls): ?>
                        <a href="index.php?page=controles&tab=resultats&res_class=<?php echo $cls['id']; ?>" 
                           class="sd-opt <?php echo $results_filter_class == $cls['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cls['nom']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($results_data) || empty($results_data['instances'])): ?>
            <div class="empty-box">
                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="rgba(0,0,0,0.13)" stroke-width="1.5" class="mb-3">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <h6 style="font-weight: 600; color: rgba(0,0,0,0.55);">Aucun résultat disponible</h6>
                <p class="text-muted mb-0" style="font-size: 13px;">
                    <?php if (empty($classes)): ?>
                        Vous n'avez pas encore de classes.
                    <?php else: ?>
                        Créez des évaluations et saisissez les notes pour voir les résultats ici.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: 
            $r_students = $results_data['students'];
            $r_instances = $results_data['instances'];
            
            // Compute class-level stats
            $graded_avgs = array_filter(array_column($r_students, 'average'), fn($v) => $v !== null);
            $class_avg = !empty($graded_avgs) ? round(array_sum($graded_avgs) / count($graded_avgs), 2) : 0;
            $pass_count = count(array_filter($graded_avgs, fn($a) => $a >= 10));
            $fail_count = count($graded_avgs) - $pass_count;
        ?>

            <!-- Stats row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05);">
                        <div style="font-size: 11px; color: rgba(0,0,0,0.4); font-weight: 500;">Stagiaires</div>
                        <div style="font-size: 22px; font-weight: 700; color: #1c1c1c;"><?php echo count($r_students); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05);">
                        <div style="font-size: 11px; color: rgba(0,0,0,0.4); font-weight: 500;">Évaluations</div>
                        <div style="font-size: 22px; font-weight: 700; color: #1c1c1c;"><?php echo count($r_instances); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background: rgba(34,197,94,0.06); border: 1px solid rgba(34,197,94,0.12);">
                        <div style="font-size: 11px; color: rgba(0,0,0,0.4); font-weight: 500;">Moyenne classe</div>
                        <div style="font-size: 22px; font-weight: 700; color: <?php echo $class_avg >= 10 ? '#22c55e' : '#ef4444'; ?>;"><?php echo $class_avg; ?>/20</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded-3" style="background: rgba(59,130,246,0.06); border: 1px solid rgba(59,130,246,0.12);">
                        <div style="font-size: 11px; color: rgba(0,0,0,0.4); font-weight: 500;">Taux de réussite</div>
                        <div style="font-size: 22px; font-weight: 700; color: #3b82f6;">
                            <?php echo count($graded_avgs) > 0 ? round(($pass_count / count($graded_avgs)) * 100) : 0; ?>%
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results table -->
            <div class="c-card p-0" style="overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size: 13px;">
                        <thead>
                            <tr style="background: rgba(0,0,0,0.02);">
                                <th style="padding: 12px 16px; font-weight: 600; color: rgba(0,0,0,0.55); white-space: nowrap; position: sticky; left: 0; background: #f9f9f9; z-index: 1; border-bottom: 2px solid rgba(0,0,0,0.06);">#</th>
                                <th style="padding: 12px 16px; font-weight: 600; color: rgba(0,0,0,0.55); white-space: nowrap; position: sticky; left: 30px; background: #f9f9f9; z-index: 1; border-bottom: 2px solid rgba(0,0,0,0.06); min-width: 160px;">Stagiaire</th>
                                <?php foreach ($r_instances as $ri): ?>
                                <th style="padding: 12px 12px; font-weight: 600; color: rgba(0,0,0,0.55); text-align: center; white-space: nowrap; border-bottom: 2px solid rgba(0,0,0,0.06); min-width: 100px;">
                                    <div style="font-size: 12px; line-height: 1.3;">
                                        <?php echo htmlspecialchars($ri['template_title']); ?>
                                        <?php if (!empty($ri['session_name'])): ?>
                                            <div style="font-size: 10px; font-weight: 400; color: rgba(0,0,0,0.35);"><?php echo htmlspecialchars($ri['session_name']); ?></div>
                                        <?php endif; ?>
                                        <div style="font-size: 10px; font-weight: 400; color: rgba(0,0,0,0.3);">/<?php echo $ri['total_points']; ?></div>
                                    </div>
                                </th>
                                <?php endforeach; ?>
                                <th style="padding: 12px 16px; font-weight: 600; color: rgba(0,0,0,0.7); text-align: center; white-space: nowrap; border-bottom: 2px solid rgba(0,0,0,0.06); background: rgba(0,0,0,0.03); min-width: 90px;">Moy. /20</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 0; foreach ($r_students as $stu): $rank++; ?>
                            <tr>
                                <td style="padding: 10px 16px; color: rgba(0,0,0,0.35); font-weight: 600; vertical-align: middle; position: sticky; left: 0; background: #fff; z-index: 1;"><?php echo $rank; ?></td>
                                <td style="padding: 10px 16px; font-weight: 500; vertical-align: middle; position: sticky; left: 30px; background: #fff; z-index: 1; white-space: nowrap;">
                                    <?php echo htmlspecialchars($stu['nom']); ?>
                                </td>
                                <?php foreach ($r_instances as $ri): 
                                    $note = $stu['results'][$ri['id']] ?? null;
                                    $maxPts = $ri['total_points'];
                                    $noteColor = '#1c1c1c';
                                    if ($note !== null) {
                                        $pct20  = ($note / $maxPts) * 20;
                                        $noteColor = $pct20 >= 10 ? '#22c55e' : ($pct20 >= 7 ? '#f59e0b' : '#ef4444');
                                    }
                                ?>
                                <td style="padding: 10px 12px; text-align: center; vertical-align: middle;">
                                    <?php if ($note !== null): ?>
                                        <span style="font-weight: 600; color: <?php echo $noteColor; ?>;"><?php echo $note; ?></span>
                                    <?php else: ?>
                                        <span style="color: rgba(0,0,0,0.15);">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                                <td style="padding: 10px 16px; text-align: center; vertical-align: middle; background: rgba(0,0,0,0.015); font-weight: 700;">
                                    <?php if ($stu['average'] !== null): ?>
                                        <span style="color: <?php echo $stu['average'] >= 10 ? '#22c55e' : ($stu['average'] >= 7 ? '#f59e0b' : '#ef4444'); ?>;">
                                            <?php echo $stu['average']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: rgba(0,0,0,0.15);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Legend -->
            <div class="d-flex flex-wrap gap-3 mt-3" style="font-size: 11px; color: rgba(0,0,0,0.4);">
                <span><span style="color: #22c55e; font-weight: 600;">&#9679;</span> ≥ 10/20</span>
                <span><span style="color: #f59e0b; font-weight: 600;">&#9679;</span> 7–10/20</span>
                <span><span style="color: #ef4444; font-weight: 600;">&#9679;</span> &lt; 7/20</span>
                <span><span style="color: rgba(0,0,0,0.15);">—</span> Non noté</span>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>
</main>

<!-- ======================================================================== -->
<!-- CREATE TEMPLATE MODAL -->
<!-- ======================================================================== -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-weight: 600; font-size: 16px;">Nouveau modèle de contrôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 500;">Titre <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required 
                               placeholder="Ex: Contrôle Continu - Développement Web" style="border-radius: 10px;">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label" style="font-size: 13px; font-weight: 500;">Niveau d'année <span class="text-danger">*</span></label>
                            <select name="year_level_id" class="form-select" required style="border-radius: 10px;">
                                <option value="">Sélectionner…</option>
                                <?php foreach ($year_levels as $yl): ?>
                                    <option value="<?php echo $yl['id']; ?>"><?php echo htmlspecialchars($yl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($year_levels)): ?>
                                <small class="text-danger" style="font-size: 11px;">Créez d'abord des niveaux dans la gestion des classes</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-5">
                            <label class="form-label" style="font-size: 13px; font-weight: 500;">Note max</label>
                            <input type="number" name="total_points" class="form-control" value="20" 
                                   min="1" max="100" step="0.5" required style="border-radius: 10px;">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="font-size: 13px; font-weight: 500;">Description <span class="text-muted fw-normal">(optionnel)</span></label>
                        <textarea name="description" class="form-control" rows="2" 
                                  placeholder="Notes sur ce modèle…" style="border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal" style="font-size: 13px;">Annuler</button>
                    <button type="submit" name="create_template" class="btn btn-dark rounded-pill px-4" style="font-size: 13px;" <?php echo empty($year_levels) ? 'disabled' : ''; ?>>Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================== -->
<!-- CREATE INSTANCE MODAL -->
<!-- ======================================================================== -->
<div class="modal fade" id="createInstanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-weight: 600; font-size: 16px;">Nouvelle évaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="p-3 rounded-3 mb-3" style="background: rgba(59,130,246,0.06); font-size: 13px; color: rgba(0,0,0,0.6);">
                        <i class="bi bi-info-circle me-1"></i>
                        Choisissez une classe, puis un modèle compatible avec son niveau.
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 500;">Classe <span class="text-danger">*</span></label>
                        <select name="class_id" id="hubSelClass" class="form-select" required style="border-radius: 10px;">
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?php echo $cls['id']; ?>" data-year-id="<?php echo $cls['year_level_id'] ?? ''; ?>"
                                    <?php echo $filter_class == $cls['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cls['nom']); ?>
                                    <?php if ($cls['year_level_id'] && isset($year_lookup[$cls['year_level_id']])): ?>
                                        (<?php echo htmlspecialchars($year_lookup[$cls['year_level_id']]); ?>)
                                    <?php else: ?> (Niveau non défini)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px; font-weight: 500;">Modèle <span class="text-danger">*</span></label>
                        <select name="template_id" id="hubSelTpl" class="form-select" required style="border-radius: 10px;">
                            <option value="">Sélectionnez d'abord une classe</option>
                        </select>
                        <small class="text-muted" id="hubTplHelp" style="font-size: 11px;">Les modèles dépendent du niveau de la classe</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="font-size: 13px; font-weight: 500;">Session <span class="text-muted fw-normal">(optionnel)</span></label>
                        <input type="text" name="session_name" class="form-control" placeholder="Ex: Session Normale 2026, Rattrapage…" style="border-radius: 10px;">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal" style="font-size: 13px;">Annuler</button>
                    <button type="submit" name="create_instance" class="btn btn-dark rounded-pill px-4" style="font-size: 13px;">Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ======================================================================
// Searchable Dropdown Logic
// ======================================================================
function sdToggle(id) {
    const wrap = document.getElementById(id);
    const wasOpen = wrap.classList.contains('open');
    // Close all others first
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
    // Show/hide empty message
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

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.sd-wrap')) {
        document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.sd-wrap.open').forEach(w => w.classList.remove('open'));
    }
});

// ======================================================================
// Instance Modal: Template filtering by class year level
// ======================================================================
const _tplByYear = <?php 
    $all_templates = $controle->getAllTemplates();
    $tby = [];
    foreach ($all_templates as $t) {
        $yid = $t['year_level_id'] ?? 0;
        if (!isset($tby[$yid])) $tby[$yid] = [];
        $tby[$yid][] = ['id'=>$t['id'],'title'=>$t['title'],'total_points'=>$t['total_points'],'total_phase_points'=>$t['total_phase_points']];
    }
    echo json_encode($tby);
?>;
document.getElementById('hubSelClass')?.addEventListener('change', function() {
    const o = this.options[this.selectedIndex];
    const yid = o.dataset.yearId || '';
    const s = document.getElementById('hubSelTpl');
    const h = document.getElementById('hubTplHelp');
    s.innerHTML = '<option value="">Sélectionner un modèle</option>';
    if (!yid) { s.innerHTML = '<option value="">Niveau non défini</option>'; h.textContent = 'Définissez le niveau de cette classe'; return; }
    const tpls = _tplByYear[yid] || [];
    if (!tpls.length) { s.innerHTML = '<option value="">Aucun modèle pour ce niveau</option>'; h.textContent = 'Créez un modèle pour ce niveau'; }
    else { tpls.forEach(t => { const opt = document.createElement('option'); opt.value = t.id; opt.textContent = t.title + ' (' + t.total_points + ' pts)'; if (Math.abs((t.total_phase_points||0)-t.total_points) > 0.01) { opt.textContent += ' ⚠ Incomplet'; opt.disabled = true; } s.appendChild(opt); }); h.textContent = tpls.length + ' modèle(s)'; }
});
if (document.getElementById('hubSelClass')?.value) document.getElementById('hubSelClass').dispatchEvent(new Event('change'));
</script>

<?php include 'views/partials/footer.php'; ?>
