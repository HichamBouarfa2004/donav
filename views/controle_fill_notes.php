<?php
/**
 * Fill Notes for a Controle Instance - Redesigned
 * Clean phase-by-phase grading with visual progress
 */

if (!isset($_SESSION['teacher_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'classes/ClassRoom.php';
require_once 'classes/Controle.php';
require_once 'classes/Team.php';
require_once 'classes/Student.php';

$database = new Database();
$db = $database->getConnection();

$classroom = new ClassRoom($db);
$controle = new Controle($db);
$team = new Team($db);
$student = new Student($db);

$instance_id = intval($_GET['id'] ?? 0);

if ($instance_id <= 0) {
    header('Location: index.php?page=controles&tab=evaluations');
    exit;
}

$instance = $controle->getInstanceById($instance_id);

if (!$instance || $instance['created_by'] != $_SESSION['teacher_id']) {
    header('Location: index.php?page=controles&tab=evaluations');
    exit;
}

$message = '';
$error = '';

// Get phases for this template
$phases = $controle->getPhases($instance['template_id']);

// Get students in the class
$students_stmt = $student->getByClass($instance['class_id']);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teams in the class
$teams_stmt = $team->getByClass($instance['class_id']);
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build student -> team lookup
$student_teams = [];
foreach ($teams as $t) {
    $team->id = $t['id'];
    $members_stmt = $team->getMembers();
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members as $m) {
        $student_teams[$m['id']] = $t['id'];
    }
}

// Handle saving notes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notes'])) {
    if ($instance['is_locked']) {
        $error = "Ce contrôle est verrouillé.";
    } else {
        $phase_id = intval($_POST['phase_id'] ?? 0);
        $phase = $controle->getPhaseById($phase_id);
        
        if ($phase) {
            $success_count = 0;
            
            if ($phase['grading_mode'] === 'individual') {
                if (isset($_POST['notes']) && is_array($_POST['notes'])) {
                    foreach ($_POST['notes'] as $student_id => $note) {
                        if ($note !== '' && is_numeric($note)) {
                            $remarks = $_POST['remarks'][$student_id] ?? null;
                            if ($controle->saveIndividualNote($instance_id, $phase_id, $student_id, floatval($note), $remarks)) {
                                $success_count++;
                            }
                        }
                    }
                }
            } else {
                if (isset($_POST['team_notes']) && is_array($_POST['team_notes'])) {
                    foreach ($_POST['team_notes'] as $team_id => $note) {
                        if ($note !== '' && is_numeric($note)) {
                            $remarks = $_POST['team_remarks'][$team_id] ?? null;
                            if ($controle->saveTeamNote($instance_id, $phase_id, $team_id, floatval($note), $remarks)) {
                                $success_count++;
                            }
                        }
                    }
                }
            }
            
            $message = "$success_count note(s) enregistrée(s).";
            $controle->computeAllResults($instance_id);
        } else {
            $error = "Phase non trouvée.";
        }
    }
}

// Current phase
$current_phase_id = isset($_GET['phase']) ? intval($_GET['phase']) : ($phases[0]['id'] ?? 0);
$current_phase = null;
foreach ($phases as $p) {
    if ($p['id'] == $current_phase_id) {
        $current_phase = $p;
        break;
    }
}

// Get existing notes for current phase
$existing_notes = [];
if ($current_phase) {
    if ($current_phase['grading_mode'] === 'individual') {
        $notes = $controle->getInstanceIndividualNotes($instance_id, $current_phase_id);
        foreach ($notes as $n) {
            $existing_notes[$n['student_id']] = $n;
        }
    } else {
        $notes = $controle->getInstanceTeamNotes($instance_id, $current_phase_id);
        foreach ($notes as $n) {
            $existing_notes[$n['team_id']] = $n;
        }
    }
}

// Get grading progress
$progress = $controle->getGradingProgress($instance_id);

include 'views/partials/header.php';
include 'views/partials/sidebar.php';
?>

<style>
.fn-page .fn-card   { border: 1px solid rgba(0,0,0,0.06); border-radius: 14px; background: #fff; }
.fn-page .fn-header  { padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,0.05); }
.fn-page .pill-nav   { display: flex; gap: 4px; flex-wrap: wrap; padding: 12px 16px; background: rgba(0,0,0,0.015); border-bottom: 1px solid rgba(0,0,0,0.05); }
.fn-page .pill-item  { padding: 6px 14px; border-radius: 10px; font-size: 13px; font-weight: 500; text-decoration: none; color: rgba(0,0,0,0.5); transition: all 0.15s; display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; }
.fn-page .pill-item:hover { color: #1c1c1c; background: rgba(0,0,0,0.03); }
.fn-page .pill-item.active { color: #1c1c1c; background: #fff; border-color: rgba(0,0,0,0.1); font-weight: 600; }
.fn-page .pill-dot   { width: 7px; height: 7px; border-radius: 50%; }
.fn-page .dot-done   { background: #22c55e; }
.fn-page .dot-partial{ background: #f59e0b; }
.fn-page .dot-empty  { background: rgba(0,0,0,0.12); }
.fn-page .note-row   { display: flex; align-items: center; gap: 12px; padding: 10px 20px; border-bottom: 1px solid rgba(0,0,0,0.03); transition: background 0.1s; }
.fn-page .note-row:hover { background: rgba(0,0,0,0.01); }
.fn-page .note-row:last-child { border-bottom: none; }
.fn-page .note-num   { width: 26px; height: 26px; border-radius: 7px; background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: rgba(0,0,0,0.35); flex-shrink: 0; }
.fn-page .note-input  { width: 90px; padding: 5px 10px; border: 1px solid rgba(0,0,0,0.1); border-radius: 8px; font-size: 13px; text-align: center; transition: border-color 0.15s; outline: none; }
.fn-page .note-input:focus { border-color: #1c1c1c; }
.fn-page .note-input.empty-note { border-color: #f59e0b; background: rgba(245,158,11,0.04); }
.fn-page .remark-input { flex: 1; min-width: 100px; padding: 5px 10px; border: 1px solid rgba(0,0,0,0.06); border-radius: 8px; font-size: 12px; color: rgba(0,0,0,0.6); outline: none; }
.fn-page .remark-input:focus { border-color: rgba(0,0,0,0.15); }
.fn-page .stat-pill   { padding: 12px 16px; border-radius: 12px; background: rgba(0,0,0,0.025); text-align: center; }
.fn-page .team-badge  { padding: 2px 8px; border-radius: 5px; font-size: 10px; font-weight: 600; background: rgba(59,130,246,0.08); color: #3b82f6; }
.fn-page .lock-banner { padding: 10px 16px; background: rgba(245,158,11,0.06); border-radius: 10px; font-size: 13px; color: #92400e; display: flex; align-items: center; gap: 8px; }
</style>

<main class="main-content d-flex flex-column" style="padding-top: 88px;">
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
                <h1 style="font-size: 15px; font-weight: 600; color: #1c1c1c; margin: 0;">Saisie des notes</h1>
            </div>
            <div class="ms-auto"></div>
        </div>
    </nav>
<div class="container-fluid fn-page" style="padding: 24px 28px; max-width: 1100px;">

    <!-- Breadcrumb -->
    <nav class="mb-3" style="font-size: 13px;">
        <a href="index.php?page=controles&tab=evaluations" class="text-decoration-none text-muted">Contrôles</a>
        <span class="text-muted mx-1">/</span>
        <span style="color: #1c1c1c; font-weight: 500;">Saisie des notes</span>
    </nav>

    <!-- Header row -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h4 class="mb-1" style="font-weight: 700; font-size: 20px;"><?php echo htmlspecialchars($instance['template_title']); ?></h4>
            <div class="d-flex flex-wrap gap-2 align-items-center" style="font-size: 13px; color: rgba(0,0,0,0.5);">
                <span class="badge rounded-pill" style="background: rgba(0,0,0,0.06); color: rgba(0,0,0,0.6); font-weight: 500;"><?php echo htmlspecialchars($instance['class_name']); ?></span>
                <?php if (!empty($instance['year_level_name'])): ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars($instance['year_level_name']); ?></span>
                <?php endif; ?>
                <span>·</span>
                <span>Sur <?php echo $instance['total_points']; ?> pts</span>
                <?php if ($instance['session_name']): ?>
                    <span>·</span>
                    <span><?php echo htmlspecialchars($instance['session_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?page=controle_instance_results&id=<?php echo $instance_id; ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3" style="font-size: 13px;">
                <i class="bi bi-bar-chart me-1"></i> Résultats
            </a>
            <a href="index.php?page=controles&tab=evaluations" class="btn btn-outline-dark btn-sm rounded-pill px-3" style="font-size: 13px;">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
        </div>
    </div>

    <?php if ($instance['is_locked']): ?>
        <div class="lock-banner mb-3">
            <i class="bi bi-lock"></i>
            Contrôle verrouillé — les notes ne peuvent plus être modifiées.
        </div>
    <?php endif; ?>

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 14px;">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size: 10px;"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="font-size: 14px;">
            <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" style="font-size: 10px;"></button>
        </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="d-flex gap-3 mb-4 flex-wrap">
        <div class="stat-pill" style="min-width: 110px;">
            <div style="font-size: 22px; font-weight: 700;"><?php echo ($progress['overall_percentage'] ?? 0); ?>%</div>
            <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Progression</div>
        </div>
        <div class="stat-pill" style="min-width: 110px;">
            <div style="font-size: 22px; font-weight: 700;"><?php echo $progress['total_filled'] ?? 0; ?><span style="font-size: 13px; font-weight: 400; color: rgba(0,0,0,0.35);">/<?php echo $progress['total_expected'] ?? 0; ?></span></div>
            <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Notes saisies</div>
        </div>
        <div class="stat-pill" style="min-width: 110px;">
            <div style="font-size: 22px; font-weight: 700;"><?php echo count($phases); ?></div>
            <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Phases</div>
        </div>
        <div class="stat-pill" style="min-width: 110px;">
            <div style="font-size: 22px; font-weight: 700;"><?php echo count($students); ?></div>
            <div style="font-size: 11px; color: rgba(0,0,0,0.4);">Élèves</div>
        </div>
    </div>

    <!-- Phase navigation pills -->
    <div class="fn-card mb-4 overflow-hidden">
        <div class="pill-nav">
            <?php foreach ($phases as $p):
                $phase_progress = null;
                foreach ($progress['phases'] ?? [] as $pp) {
                    if ($pp['phase_id'] == $p['id']) { $phase_progress = $pp; break; }
                }
                $pct = $phase_progress['percentage'] ?? 0;
                $dot_class = $pct == 100 ? 'dot-done' : ($pct > 0 ? 'dot-partial' : 'dot-empty');
            ?>
                <a href="index.php?page=controle_fill_notes&id=<?php echo $instance_id; ?>&phase=<?php echo $p['id']; ?>" 
                   class="pill-item <?php echo $current_phase_id == $p['id'] ? 'active' : ''; ?>">
                    <span class="pill-dot <?php echo $dot_class; ?>"></span>
                    <?php echo htmlspecialchars($p['title']); ?>
                    <span style="font-size: 11px; color: rgba(0,0,0,0.3);"><?php echo $p['points']; ?>pts</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!$current_phase): ?>
            <div class="text-center py-5" style="color: rgba(0,0,0,0.35);">
                <i class="bi bi-list-columns" style="font-size: 32px;"></i>
                <p class="mb-0 mt-2" style="font-size: 13px;">Aucune phase définie pour ce modèle.</p>
            </div>
        <?php else: ?>
            <!-- Phase header -->
            <div class="fn-header d-flex justify-content-between align-items-center">
                <div>
                    <strong style="font-size: 15px;"><?php echo htmlspecialchars($current_phase['title']); ?></strong>
                    <span style="font-size: 12px; color: rgba(0,0,0,0.4); margin-left: 8px;">
                        <?php echo $current_phase['points']; ?> pts · <?php echo $current_phase['grading_mode'] === 'team' ? 'Mode Équipe' : 'Mode Individuel'; ?>
                    </span>
                </div>
                <span style="padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; background: <?php echo $current_phase['grading_mode'] === 'team' ? 'rgba(59,130,246,0.1)' : 'rgba(0,0,0,0.05)'; ?>; color: <?php echo $current_phase['grading_mode'] === 'team' ? '#3b82f6' : 'rgba(0,0,0,0.55)'; ?>;">
                    <i class="bi bi-<?php echo $current_phase['grading_mode'] === 'team' ? 'people' : 'person'; ?>"></i>
                    <?php echo $current_phase['grading_mode'] === 'team' ? 'Équipe' : 'Individuel'; ?>
                </span>
            </div>

            <!-- Grading form -->
            <form method="POST">
                <input type="hidden" name="phase_id" value="<?php echo $current_phase['id']; ?>">

                <?php if ($current_phase['grading_mode'] === 'individual'): ?>
                    <!-- Column headers -->
                    <div class="d-flex align-items-center gap-3 px-3 py-2" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(0,0,0,0.05);">
                        <div style="width: 26px;"></div>
                        <div style="flex: 2; min-width: 140px;">Élève</div>
                        <div style="width: 90px; text-align: center;">Note /<?php echo $current_phase['points']; ?></div>
                        <div style="flex: 1; min-width: 100px;">Remarque</div>
                    </div>

                    <?php foreach ($students as $index => $s):
                        $existing = $existing_notes[$s['id']] ?? null;
                        $has_note = $existing && $existing['note'] !== null;
                    ?>
                        <div class="note-row">
                            <div class="note-num"><?php echo $index + 1; ?></div>
                            <div style="flex: 2; min-width: 140px;">
                                <div style="font-size: 13px; font-weight: 500;"><?php echo htmlspecialchars($s['nom']); ?></div>
                                <?php if (isset($student_teams[$s['id']])): ?>
                                    <span class="team-badge">
                                        <?php foreach ($teams as $t) { if ($t['id'] == $student_teams[$s['id']]) { echo htmlspecialchars($t['name']); break; } } ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <input type="number" name="notes[<?php echo $s['id']; ?>]" 
                                       class="note-input <?php echo !$has_note ? 'empty-note' : ''; ?>"
                                       value="<?php echo $has_note ? $existing['note'] : ''; ?>"
                                       placeholder="—"
                                       min="0" max="<?php echo $current_phase['points']; ?>" step="0.25"
                                       <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                                <span style="font-size: 12px; color: rgba(0,0,0,0.35); white-space: nowrap;">/<?php echo $current_phase['points']; ?></span>
                            </div>
                            <input type="text" name="remarks[<?php echo $s['id']; ?>]" 
                                   class="remark-input"
                                   value="<?php echo $existing ? htmlspecialchars($existing['remarks'] ?? '') : ''; ?>"
                                   placeholder="Optionnel"
                                   <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                        </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <!-- Team grading -->
                    <?php if (empty($teams)): ?>
                        <div class="text-center py-5" style="color: rgba(0,0,0,0.4);">
                            <i class="bi bi-people" style="font-size: 32px;"></i>
                            <p class="mb-1 mt-2" style="font-size: 14px; font-weight: 500;">Aucune équipe définie</p>
                            <a href="index.php?page=teams&class_id=<?php echo $instance['class_id']; ?>" style="font-size: 13px;">Créer des équipes</a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex align-items-center gap-3 px-3 py-2" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(0,0,0,0.05);">
                            <div style="width: 26px;"></div>
                            <div style="flex: 2;">Équipe</div>
                            <div style="width: 110px; text-align: center;">Note /<?php echo $current_phase['points']; ?></div>
                            <div style="flex: 1; min-width: 100px;">Remarque</div>
                        </div>

                        <?php foreach ($teams as $index => $t):
                            $existing = $existing_notes[$t['id']] ?? null;
                            $has_note = $existing && $existing['note'] !== null;
                            $team->id = $t['id'];
                            $members_stmt = $team->getMembers();
                            $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                            $member_colors = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#ef4444','#6366f1','#14b8a6'];
                        ?>
                            <div class="note-row" style="align-items: flex-start;">
                                <div class="note-num" style="margin-top: 4px;"><?php echo $index + 1; ?></div>
                                <div style="flex: 2;">
                                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($members as $mi => $m): 
                                            $color = $member_colors[$mi % count($member_colors)];
                                            $initials = mb_strtoupper(mb_substr($m['nom'], 0, 1));
                                        ?>
                                            <span title="<?php echo htmlspecialchars($m['nom']); ?>" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px 2px 2px; border-radius: 12px; background: <?php echo $color; ?>12; font-size: 11px; font-weight: 500; color: <?php echo $color; ?>;">
                                                <span style="width: 18px; height: 18px; border-radius: 50%; background: <?php echo $color; ?>22; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700;"><?php echo $initials; ?></span>
                                                <?php echo htmlspecialchars($m['nom']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <input type="number" name="team_notes[<?php echo $t['id']; ?>]" 
                                           class="note-input <?php echo !$has_note ? 'empty-note' : ''; ?>"
                                           value="<?php echo $has_note ? $existing['note'] : ''; ?>"
                                           placeholder="—"
                                           min="0" max="<?php echo $current_phase['points']; ?>" step="0.25"
                                           <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                                    <span style="font-size: 12px; color: rgba(0,0,0,0.35); white-space: nowrap;">/<?php echo $current_phase['points']; ?></span>
                                </div>
                                <input type="text" name="team_remarks[<?php echo $t['id']; ?>]" 
                                       class="remark-input"
                                       value="<?php echo $existing ? htmlspecialchars($existing['remarks'] ?? '') : ''; ?>"
                                       placeholder="Optionnel"
                                       <?php echo $instance['is_locked'] ? 'disabled' : ''; ?>>
                            </div>
                        <?php endforeach; ?>

                        <div class="px-4 py-2" style="background: rgba(59,130,246,0.03); font-size: 12px; color: rgba(0,0,0,0.4);">
                            <i class="bi bi-info-circle me-1"></i> La note d'équipe est appliquée identiquement à chaque membre.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Save button -->
                <?php if (!$instance['is_locked'] && $current_phase): ?>
                    <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-top: 1px solid rgba(0,0,0,0.05);">
                        <span style="font-size: 12px; color: rgba(0,0,0,0.35);">Les notes sont enregistrées par phase.</span>
                        <button type="submit" name="save_notes" class="btn btn-dark btn-sm rounded-pill px-4" style="font-size: 13px;">
                            <i class="bi bi-check-lg me-1"></i> Enregistrer
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

</div>
</main>

<?php include 'views/partials/footer.php'; ?>
